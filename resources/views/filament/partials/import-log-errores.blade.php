<div class="space-y-2 text-sm">
    <ul class="list-disc pl-5">
        @foreach($errores as $e)
            <li>{{ $e }}</li>
        @endforeach
    </ul>
</div>
