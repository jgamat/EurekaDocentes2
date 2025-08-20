<?php

namespace App\Filament\Pages;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\ExperienciaAdmision;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class ConsultarCargos extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Administración de Locales';
    protected static ?string $title = 'Consultar Cargos';
    protected static string $view = 'filament.pages.consultar-cargos';

    public array $filters = [
        'proceso_id' => null,
        'proceso_fecha_id' => null,
    ];

    public function mount(): void
    {
        $abierto = Proceso::where('pro_iAbierto', true)->first();
        if ($abierto) {
            $this->filters['proceso_id'] = $abierto->pro_iCodigo;
            $activa = $abierto->procesoFecha()->where('profec_iActivo', true)->first();
            if ($activa) {
                $this->filters['proceso_fecha_id'] = $activa->profec_iCodigo;
            }
        }
        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('proceso_id')
                    ->label('Proceso Abierto')
                    ->options(Proceso::where('pro_iAbierto', true)->orderBy('pro_vcNombre')->pluck('pro_vcNombre', 'pro_iCodigo'))
                    ->reactive()
                    ->afterStateUpdated(function ($state) {
                        $this->filters['proceso_id'] = $state;
                        $this->filters['proceso_fecha_id'] = null;
                    })
                    ->extraAttributes(['class'=>'w-full','style'=>'min-width:420px;'])
                    ->columnSpan(12),
                Select::make('proceso_fecha_id')
                    ->label('Fecha Activa')
                    ->options(function(){
                        $pid = $this->filters['proceso_id'] ?? null;
                        if(!$pid) return [];
                        return ProcesoFecha::where('pro_iCodigo',$pid)
                            ->where('profec_iActivo', true)
                            ->orderBy('profec_dFecha')
                            ->pluck('profec_dFecha','profec_iCodigo');
                    })
                    ->reactive()
                    ->afterStateUpdated(fn($state)=> $this->filters['proceso_fecha_id'] = $state)
                    ->extraAttributes(['class'=>'w-full','style'=>'min-width:420px;'])
                    ->columnSpan(12),
            ])
            ->columns(12)
            ->statePath('filters');
    }

    protected function getCargoIds(): Collection
    {
        $fecha = $this->filters['proceso_fecha_id'] ?? null;
        if(!$fecha) return collect();
        $doc = DB::table('procesodocente')->where('profec_iCodigo',$fecha)->whereNotNull('expadm_iCodigo')->where('prodoc_iAsignacion',true)->pluck('expadm_iCodigo');
        $adm = DB::table('procesoadministrativo')->where('profec_iCodigo',$fecha)->whereNotNull('expadm_iCodigo')->where('proadm_iAsignacion',true)->pluck('expadm_iCodigo');
        $alu = DB::table('procesoalumno')->where('profec_iCodigo',$fecha)->whereNotNull('expadm_iCodigo')->where('proalu_iAsignacion',true)->pluck('expadm_iCodigo');
        return $doc->merge($adm)->merge($alu)->filter()->unique();
    }

    protected function getTableQuery(): Builder
    {
        $ids = $this->getCargoIds();
        if($ids->isEmpty()){
            return ExperienciaAdmision::query()->whereRaw('1=0');
        }
        return ExperienciaAdmision::query()
            ->with('maestro')
            ->whereIn('expadm_iCodigo',$ids->values());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn()=> $this->buildHeading())
            ->query(fn()=> $this->getTableQuery())
            ->columns([
                TextColumn::make('expadm_iCodigo')->label('Código Cargo')->sortable()->searchable(),
                
                TextColumn::make('maestro.expadmma_vcNombre')->label('Nombre Cargo')->sortable()->searchable()->wrap(),
                TextInputColumn::make('expadm_fMonto')
                    ->label('Monto')
                    ->type('number')
                    ->rules(['nullable','numeric','min:0'])
                    ->step('0.01')
                    ->sortable()
                    ->extraAttributes(['class'=>'text-right'])
                    ->afterStateUpdated(function($record,$state){ /* hook para lógica adicional */ }),
            ])
            ->emptyStateHeading('Seleccione filtros para ver resultados')
            ->actions([])
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25,50,100])
            ->headerActions([
                Action::make('exportar_excel')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn()=> $this->exportExcel())
                    ->disabled(fn()=> !$this->filters['proceso_fecha_id'] || $this->getCargoIds()->isEmpty())
            ]);
    }

    protected function buildHeading(): string
    {
        $hora = now()->format('H:i:s');
        return "Cargos utilizados a la hora {$hora}";
    }

    protected function exportExcel()
    {
        $ids = $this->getCargoIds();
        if($ids->isEmpty()){
            Notification::make()->title('No hay cargos para exportar')->warning()->send();
            return null;
        }
    $cargos = ExperienciaAdmision::with('maestro')->whereIn('expadm_iCodigo',$ids)->get();
    $fechaSeleccionada = optional(ProcesoFecha::find($this->filters['proceso_fecha_id']))->profec_dFecha;
    $rows = $cargos->values()->map(function($c,$i) use ($fechaSeleccionada){
            return [
                'nro'=>$i+1,
                'codigo_cargo'=>$c->expadm_iCodigo,
                'nombre_cargo'=>$c->maestro?->expadmma_vcNombre ?? '',
        'fecha_seleccionada'=>$fechaSeleccionada,
        'expadm_fMonto'=>$c->expadm_fMonto,
            ];
        });

        $now = now();
        $filename = 'cargos_utilizados_'.$now->format('Ymd_His').'.xlsx';
        $title = 'Cargos utilizados al '.$now->format('d/m/Y H:i:s');
        return Excel::download(new class($rows,$title) implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize, WithStyles {
            public function __construct(private Collection $rows, private string $title){}
            public function collection(){ return $this->rows; }
            public function headings(): array {
                if($this->rows->isEmpty()) return [];
                $keys = array_keys($this->rows->first());
                $titleRow = [$this->title];
                for($i=1;$i<count($keys);$i++){ $titleRow[]=''; }
                $blank = array_fill(0,count($keys),'');
                return [$titleRow,$blank,$keys];
            }
            public function registerEvents(): array {
                return [
                    \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event){
                        $sheet = $event->sheet->getDelegate();
                        if($this->rows->isEmpty()) return;
                        $keys = array_keys($this->rows->first());
                        $lastCol = Coordinate::stringFromColumnIndex(count($keys));
                        $sheet->mergeCells("A1:{$lastCol}1");
                        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                        $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true);
                        $endRow = 3 + $this->rows->count();
                        $sheet->getStyle("A3:{$lastCol}{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    }
                ];
            }
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array { return []; }
        }, $filename);
    }
}
