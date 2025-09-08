<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoFecha extends Model
{
    protected $table = 'procesofecha';
    protected $primaryKey = 'profec_iCodigo';
    public $incrementing = true;

    protected $fillable = [
        'profec_iCodigo',
        'pro_iCodigo',
        'profec_dFecha',
        'profec_iActivo',
        'profec_vcUrlAnverso',
        'profec_vcUrlReverso',
    ];

    public function proceso(): BelongsTo
    {
        // Foreign key en esta tabla: pro_iCodigo -> clave primaria en proceso: pro_iCodigo
        return $this->belongsTo(Proceso::class, 'pro_iCodigo', 'pro_iCodigo');
    }

     public function localesMaestro(): BelongsToMany
    {
        return $this->belongsToMany(
            LocalesMaestro::class,
            'locales', 
            'profec_iCodigo',   
            'locma_iCodigo', 
        );
    }

    public function experiencias(): HasMany
    {
        return $this->hasMany(ExperienciaAdmision::class, 'profec_iCodigo');
    }

     protected $casts = [
        'profec_iActivo' => 'boolean', 
        
    ];

    // Accessors to normalize stored values on read (used by Filament FileUpload state)
    public function getProfecVcUrlAnversoAttribute($value)
    {
        if (!is_string($value) || $value === '') return $value;
        $path = ltrim($value, '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parts = @parse_url($path);
            $path = is_array($parts) && isset($parts['path']) ? ltrim($parts['path'], '/') : $path;
        }
        if (str_starts_with($path, 'public/')) $path = substr($path, 7);
        if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
        return $path;
    }

    public function getProfecVcUrlReversoAttribute($value)
    {
        if (!is_string($value) || $value === '') return $value;
        $path = ltrim($value, '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parts = @parse_url($path);
            $path = is_array($parts) && isset($parts['path']) ? ltrim($parts['path'], '/') : $path;
        }
        if (str_starts_with($path, 'public/')) $path = substr($path, 7);
        if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
        return $path;
    }

    // Normalize setters to store relative public-disk paths
    public function setProfecVcUrlAnversoAttribute($value): void
    {
        if (!is_string($value) || $value === '') { $this->attributes['profec_vcUrlAnverso'] = $value; return; }
        $path = ltrim($value, '/');
        if (str_starts_with($path, 'public/')) $path = substr($path, 7);
        if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
        $this->attributes['profec_vcUrlAnverso'] = $path;
    }

    public function setProfecVcUrlReversoAttribute($value): void
    {
        if (!is_string($value) || $value === '') { $this->attributes['profec_vcUrlReverso'] = $value; return; }
        $path = ltrim($value, '/');
        if (str_starts_with($path, 'public/')) $path = substr($path, 7);
        if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
        $this->attributes['profec_vcUrlReverso'] = $path;
    }

    // Accessors to return full public URL for convenience when needed
    public function getProfecVcUrlAnversoUrlAttribute(): ?string
    {
        $path = $this->attributes['profec_vcUrlAnverso'] ?? null;
        if (!$path) return null;
        return asset('storage/' . ltrim($path, '/'));
    }

    public function getProfecVcUrlReversoUrlAttribute(): ?string
    {
        $path = $this->attributes['profec_vcUrlReverso'] ?? null;
        if (!$path) return null;
        return asset('storage/' . ltrim($path, '/'));
    }
}
