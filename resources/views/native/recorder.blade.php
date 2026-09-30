@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full bg-theme-background {{ ($tenantCode === null || $replacingTenant) ? 'safe-area-top' : '' }}">
    @if ($tenantCode === null || $replacingTenant)
        <native:scroll-view class="w-full flex-1">
            <native:column class="w-full px-6 pt-4 pb-10 gap-5">
                <native:row class="w-full justify-end">
                    @include('native.language-switch')
                </native:row>

                <native:image
                    src="brand/yard.jpg"
                    alt="A grey horse in the yard"
                    class="w-full h-40 rounded-3xl object-cover"
                />

                <native:row class="w-full items-center gap-3">
                    @if ($this->headerMarkTint())
                        <native:image src="brand/mark.png" alt="Stable Mixer" class="w-16 h-16" tintColor="{{ $this->headerMarkTint() }}" />
                    @else
                        <native:image src="brand/mark.png" alt="Stable Mixer" class="w-16 h-16" />
                    @endif
                    <native:column class="flex-1 gap-1">
                        <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-secondary">Stable Mixer</native:text>
                        <native:text font="display" class="text-3xl tracking-tight text-theme-on-background">{{ __('ui.pair_title') }}</native:text>
                    </native:column>
                </native:row>

                <native:text class="text-base leading-relaxed text-theme-on-surface-variant">
                    {{ __('ui.pair_body') }}
                </native:text>

                @if ($notice)
                    <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-3 border border-theme-outline">
                        <native:text class="text-theme-destructive">{{ $notice }}</native:text>
                    </native:column>
                @endif

                <native:pressable ref="demo" @tap="useDemoTenant" class="w-full bg-theme-surface border border-theme-outline py-4 rounded-2xl items-center">
                    <native:text font="display" class="text-theme-on-surface text-lg">{{ __('ui.demo') }}</native:text>
                </native:pressable>

                @if ($this->scannerAvailable())
                    <native:pressable ref="scan" @tap="scanTenant" class="w-full bg-theme-primary py-4 rounded-2xl items-center shadow-sm">
                        <native:text font="display" class="text-theme-on-primary text-lg">{{ __('ui.scan') }}</native:text>
                    </native:pressable>
                @endif

                <native:column class="w-full gap-4 rounded-3xl bg-theme-surface p-4 shadow-sm border border-theme-outline">
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ __('ui.or_type') }}</native:text>

                    <native:outlined-text-input
                        label="{{ __('ui.tenant_code') }}"
                        placeholder="ABCD1234"
                        native:model="typedCode"
                        class="w-full"
                    />

                    <native:pressable ref="save-code" @tap="saveTypedCode" class="w-full bg-theme-on-background py-4 rounded-2xl items-center">
                        <native:text font="display" class="text-theme-background text-lg">{{ __('ui.save_code') }}</native:text>
                    </native:pressable>
                </native:column>

                @if ($replacingTenant && $tenantCode)
                    <native:pressable ref="cancel-tenant" @tap="cancelReplaceTenant" class="items-center py-3">
                        <native:text font="display" class="text-theme-on-surface-variant">{{ __('ui.cancel') }}</native:text>
                    </native:pressable>
                @endif
            </native:column>
        </native:scroll-view>
    @else
        @php
            $recordFill = match ($phase) {
                'recording' => 'bg-theme-destructive',
                'paused' => 'bg-theme-on-background',
                default => 'bg-theme-primary',
            };
            $recordInk = $phase === 'paused' ? 'text-theme-background' : 'text-theme-on-primary';
            $phaseLabel = $this->phaseLabel();
        @endphp

        <native:column class="w-full flex-1 px-5 pt-3 pb-2 gap-3 safe-area-top">
            <native:row class="w-full items-center gap-3">
                @if ($this->headerMarkTint())
                    <native:image src="brand/mark.png" alt="" class="w-11 h-11" tintColor="{{ $this->headerMarkTint() }}" />
                @else
                    <native:image src="brand/mark.png" alt="" class="w-11 h-11" />
                @endif
                <native:column class="flex-1 gap-0.5">
                    <native:text font="display" class="text-xl tracking-tight text-theme-on-background">{{ $stableName ?: 'Stable Mixer' }}</native:text>
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ $tenantCode }}</native:text>
                </native:column>
                @include('native.language-switch')
                <native:pressable ref="change-tenant" @tap="beginReplaceTenant" class="px-4 py-2 rounded-full bg-theme-surface border border-theme-outline">
                    <native:text font="display" class="text-theme-on-surface">{{ __('ui.change') }}</native:text>
                </native:pressable>
            </native:row>

            @if ($tab === 'stable')
                <native:scroll-view class="w-full flex-1">
                    <native:column class="w-full gap-2 pb-4">
                        @forelse ($records as $row)
                            <native:column native:key="rec-row-{{ $row['id'] }}" class="w-full gap-2 rounded-2xl bg-theme-surface px-4 py-3 border border-theme-outline">
                                <native:row class="w-full items-center justify-between">
                                    <native:text font="display" class="text-lg tracking-tight text-theme-on-background">{{ $row['name'] }}</native:text>
                                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ __('ui.kind_'.$row['kind']) }}</native:text>
                                </native:row>
                                @if ($row['knowledge'] !== '')
                                    <native:text class="text-base leading-relaxed text-theme-on-surface">{{ $row['knowledge'] }}</native:text>
                                @endif
                                @if ($row['events'] !== [])
                                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ __('ui.events') }}</native:text>
                                    @foreach ($row['events'] as $event)
                                        <native:column class="w-full gap-0.5">
                                            <native:text class="text-sm text-theme-on-surface">{{ $event['when'] !== '' ? $event['when'].' · ' : '' }}{{ $event['summary'] }}</native:text>
                                            @if ($event['detail'] !== '')
                                                <native:text class="text-sm text-theme-on-surface-variant">{{ $event['detail'] }}</native:text>
                                            @endif
                                        </native:column>
                                    @endforeach
                                @endif
                            </native:column>
                        @empty
                            <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-5 border border-theme-outline">
                                <native:text class="text-theme-on-surface-variant">{{ __('ui.nothing_in_stable') }}</native:text>
                            </native:column>
                        @endforelse
                    </native:column>
                </native:scroll-view>
            @elseif ($tab === 'ask')
                <native:scroll-view class="w-full flex-1">
                    <native:column class="w-full gap-2 pb-4">
                        @if ($this->waitingForAnswer())
                            <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-3 border border-theme-outline">
                                <native:text class="text-theme-on-surface">{{ __('ui.waiting') }}</native:text>
                            </native:column>
                        @endif

                        @forelse ($answers as $row)
                            <native:column native:key="answer-{{ $row['id'] }}" class="w-full gap-2 rounded-2xl bg-theme-surface px-4 py-3 border border-theme-outline">
                                @if ($row['when'] !== '')
                                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ $row['when'] }}</native:text>
                                @endif
                                <native:text font="display" class="text-lg tracking-tight text-theme-on-background">{{ $row['question'] }}</native:text>
                                <native:text class="text-base leading-relaxed text-theme-on-surface">{{ $row['answer'] }}</native:text>
                                <native:row>
                                    <native:pressable @tap="playAnswer('{{ $row['id'] }}')" class="px-4 py-2 rounded-full bg-theme-primary">
                                        <native:text font="display" class="text-theme-on-primary">{{ $speakingId === $row['id'] ? __('ui.stop') : __('ui.play') }}</native:text>
                                    </native:pressable>
                                </native:row>
                            </native:column>
                        @empty
                            @unless ($this->waitingForAnswer())
                                <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-5 border border-theme-outline">
                                    <native:text class="text-theme-on-surface-variant">{{ __('ui.nothing_answered') }}</native:text>
                                </native:column>
                            @endunless
                        @endforelse
                    </native:column>
                </native:scroll-view>

                <native:column class="w-full items-center gap-3 py-2">
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-secondary">{{ $phaseLabel }}</native:text>

                    <native:pressable
                        ref="ask"
                        @tap="cycleRecording"
                        class="w-full bg-theme-primary py-4 rounded-2xl items-center shadow-sm"
                    >
                        <native:text font="display" class="text-theme-on-primary text-lg">{{ $this->recordLabel() }}</native:text>
                    </native:pressable>

                    @if ($phase !== 'idle')
                        <native:pressable ref="stop" @tap="stopRecording" class="px-10 py-3 rounded-full bg-theme-surface border border-theme-outline">
                            <native:text font="display" class="text-theme-on-surface text-lg">{{ __('ui.stop') }}</native:text>
                        </native:pressable>
                    @endif

                    @if ($notice)
                        <native:text class="text-theme-on-surface text-center">{{ $notice }}</native:text>
                    @endif
                </native:column>
            @else
                <native:column class="w-full items-center gap-3 py-2">
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-secondary">{{ $phaseLabel }}</native:text>

                    <native:pressable
                        ref="record"
                        @tap="cycleRecording"
                        class="w-48 h-48 rounded-full {{ $recordFill }} items-center justify-center shadow-md"
                    >
                        <native:text font="display" class="{{ $recordInk }} text-3xl tracking-tight">{{ $this->recordLabel() }}</native:text>
                    </native:pressable>

                    @if ($phase !== 'idle')
                        <native:pressable ref="stop" @tap="stopRecording" class="px-10 py-3 rounded-full bg-theme-surface border border-theme-outline">
                            <native:text font="display" class="text-theme-on-surface text-lg">{{ __('ui.stop') }}</native:text>
                        </native:pressable>
                    @endif

                    @if ($notice)
                        <native:text class="text-theme-on-surface text-center">{{ $notice }}</native:text>
                    @endif
                </native:column>

                <native:row class="w-full items-center justify-between">
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ __('ui.on_this_phone') }}</native:text>
                    <native:column class="rounded-full bg-theme-surface px-3 py-1 border border-theme-outline">
                        <native:text font="mono" class="text-xs text-theme-on-surface">{{ __('ui.pending_sync', ['count' => $pendingCount]) }}</native:text>
                    </native:column>
                </native:row>

                <native:scroll-view class="w-full flex-1">
                    <native:column class="w-full gap-2 pb-4">
                        @forelse ($recent as $row)
                            @php
                                $statusColor = match ($row['status']) {
                                    'failed' => 'text-theme-destructive',
                                    'synced' => 'text-theme-primary',
                                    'uploading' => 'text-theme-secondary',
                                    default => 'text-theme-on-surface-variant',
                                };
                            @endphp
                            <native:column native:key="rec-{{ $row['id'] }}" class="w-full gap-1 rounded-2xl bg-theme-surface px-3 py-2 border border-theme-outline">
                                <native:row class="w-full items-center justify-between">
                                    <native:text class="text-sm text-theme-on-surface-variant">{{ $row['when'] }}</native:text>
                                    <native:text font="display" class="text-lg tracking-tight text-theme-on-surface">{{ $row['duration'] }}</native:text>
                                </native:row>
                                <native:row class="w-full items-center justify-between">
                                    <native:text class="text-sm {{ $statusColor }}">{{ $row['label'] }} · {{ $row['size'] }}</native:text>
                                    <native:row class="gap-2">
                                        @if ($row['status'] === 'failed')
                                            <native:pressable @tap="retryRecording('{{ $row['id'] }}')" class="px-3 py-2 rounded-full bg-theme-primary">
                                                <native:text font="display" class="text-theme-on-primary">{{ __('ui.retry') }}</native:text>
                                            </native:pressable>
                                        @endif
                                        @if ($confirmingDelete === $row['id'])
                                            <native:pressable @tap="deleteRecording('{{ $row['id'] }}')" class="px-3 py-2 rounded-full bg-theme-destructive">
                                                <native:text font="display" class="text-theme-on-destructive">{{ __('ui.confirm') }}</native:text>
                                            </native:pressable>
                                            <native:pressable @tap="keepRecording" class="px-3 py-2 rounded-full bg-theme-surface-variant">
                                                <native:text font="display" class="text-theme-on-surface">{{ __('ui.keep') }}</native:text>
                                            </native:pressable>
                                        @else
                                            <native:pressable @tap="deleteRecording('{{ $row['id'] }}')" class="px-2 py-1">
                                                <native:text class="text-theme-on-surface-variant">{{ __('ui.delete') }}</native:text>
                                            </native:pressable>
                                        @endif
                                    </native:row>
                                </native:row>
                            </native:column>
                        @empty
                            <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-5 border border-theme-outline">
                                <native:text class="text-theme-on-surface-variant">{{ __('ui.nothing_recorded') }}</native:text>
                            </native:column>
                        @endforelse
                    </native:column>
                </native:scroll-view>
            @endif
        </native:column>

        <native:row class="w-full px-5 pb-4 safe-area-bottom">
            <native:row class="w-full rounded-full bg-theme-surface border border-theme-outline p-1">
                <native:pressable @tap="showStable" class="flex-1 items-center gap-1 py-2 rounded-full {{ $tab === 'stable' ? 'bg-theme-primary' : '' }}">
                    @if ($this->dockMarkTint())
                        <native:image src="brand/mark.png" alt="" class="w-6 h-6" tintColor="{{ $this->dockMarkTint() }}" />
                    @else
                        <native:image src="brand/mark.png" alt="" class="w-6 h-6" />
                    @endif
                    <native:text class="text-xs {{ $tab === 'stable' ? 'text-theme-on-primary' : 'text-theme-on-surface-variant' }}">{{ __('ui.stable') }}</native:text>
                </native:pressable>
                <native:pressable @tap="showRecord" class="flex-1 items-center gap-1 py-2 rounded-full {{ $tab === 'record' ? 'bg-theme-primary' : '' }}">
                    <native:icon
                        name="mic"
                        :ios="Ios::Mic"
                        :android="Android::Mic"
                        size="22"
                        color="{{ theme($tab === 'record' ? 'on-primary' : 'on-surface-variant') }}"
                    />
                    <native:text class="text-xs {{ $tab === 'record' ? 'text-theme-on-primary' : 'text-theme-on-surface-variant' }}">{{ __('ui.record') }}</native:text>
                </native:pressable>
                <native:pressable @tap="showAsk" class="flex-1 items-center gap-1 py-2 rounded-full {{ $tab === 'ask' ? 'bg-theme-primary' : '' }}">
                    <native:icon
                        name="question_answer"
                        :ios="Ios::QuestionmarkBubble"
                        :android="Android::QuestionAnswer"
                        size="22"
                        color="{{ theme($tab === 'ask' ? 'on-primary' : 'on-surface-variant') }}"
                    />
                    <native:text class="text-xs {{ $tab === 'ask' ? 'text-theme-on-primary' : 'text-theme-on-surface-variant' }}">{{ __('ui.ask') }}</native:text>
                </native:pressable>
            </native:row>
        </native:row>
    @endif
</native:column>
