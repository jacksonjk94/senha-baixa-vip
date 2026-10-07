@php
    $etapas = ['Telefone', 'Código', 'Horário', 'Grupo', 'Confirmar', 'Concluído'];
@endphp
<div class="flex items-start justify-between w-full mb-8 px-1 md:px-2 gap-0.5">
    @foreach ($etapas as $i => $nome)
        @php $n = $i + 1; @endphp
        @if ($n < $etapaAtual || ($n === $etapaAtual && $nome === 'Concluído'))
            <div class="flex flex-col items-center flex-1 min-w-0">
                <div class="h-8 md:h-10 flex items-center justify-center">
                    <div class="{{ $n === $etapaAtual ? 'w-8 h-8 md:w-10 md:h-10 text-sm md:text-base font-bold ring-2 md:ring-4 ring-emerald-100' : 'w-6 h-6 md:w-8 md:h-8 text-xs md:text-sm font-semibold' }} rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-sm af-step-feita"><span class="af-step-check">✓</span></div>
                </div>
                <span class="mt-1.5 md:mt-2 text-[10px] md:text-sm leading-tight {{ $n === $etapaAtual ? 'text-emerald-700 font-semibold' : 'text-emerald-700 font-medium' }} text-center break-words">{{ $nome }}</span>
            </div>
            @if (! $loop->last)
                <div class="flex-1 h-0.5 bg-emerald-200 mx-0.5 md:mx-3 self-start mt-[15px] md:mt-[19px]"></div>
            @endif
        @elseif ($n === $etapaAtual)
            <div class="flex flex-col items-center flex-1 min-w-0">
                <div class="h-8 md:h-10 flex items-center justify-center">
                    <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-blue-900 text-white flex items-center justify-center text-sm md:text-base font-bold shadow-md ring-2 md:ring-4 ring-blue-100 af-step-atual">{{ $n }}</div>
                </div>
                <span class="mt-1.5 md:mt-2 text-[11px] md:text-base leading-tight text-blue-900 font-semibold text-center break-words">{{ $nome }}</span>
            </div>
            @if (! $loop->last)
                <div class="flex-1 h-0.5 bg-slate-200 mx-0.5 md:mx-3 self-start mt-[15px] md:mt-[19px]"></div>
            @endif
        @else
            <div class="flex flex-col items-center flex-1 min-w-0">
                <div class="h-8 md:h-10 flex items-center justify-center">
                    <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xs md:text-sm font-medium border border-slate-200">{{ $n }}</div>
                </div>
                <span class="mt-1.5 md:mt-2 text-[10px] md:text-sm leading-tight text-slate-400 text-center break-words">{{ $nome }}</span>
            </div>
            @if (! $loop->last)
                <div class="flex-1 h-0.5 bg-slate-100 mx-0.5 md:mx-3 self-start mt-[15px] md:mt-[19px]"></div>
            @endif
        @endif
    @endforeach
</div>
