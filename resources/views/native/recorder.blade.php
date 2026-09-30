<native:column class="w-full h-full bg-theme-background safe-area-top">
    @if ($tenantCode === null || $replacingTenant)
        <native:scroll-view class="w-full flex-1">
            <native:column class="w-full px-6 pt-4 pb-10 gap-5">
                <native:image
                    src="brand/yard.jpg"
                    alt="A grey horse in the yard"
                    class="w-full h-40 rounded-3xl object-cover"
                />

                <native:row class="w-full items-center gap-3">
                    <native:image src="brand/mark.png" alt="Stable Mixer" class="w-16 h-16" />
                    <native:column class="flex-1 gap-1">
                        <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-secondary">Stable Mixer</native:text>
                        <native:text font="display" class="text-3xl tracking-tight text-theme-on-background">Pair this phone</native:text>
                    </native:column>
                </native:row>

                <native:text class="text-base leading-relaxed text-theme-on-surface-variant">
                    Scan the stable QR code, or type the 8-character pairing code. The phone checks it with the server before you can record.
                </native:text>

                @if ($notice)
                    <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-3 border border-theme-outline">
                        <native:text class="text-theme-destructive">{{ $notice }}</native:text>
                    </native:column>
                @endif

                <native:pressable ref="demo" @tap="useDemoTenant" class="w-full bg-theme-surface border border-theme-outline py-4 rounded-2xl items-center">
                    <native:text font="display" class="text-theme-on-surface text-lg">Use demo code</native:text>
                </native:pressable>

                @if ($this->scannerAvailable())
                    <native:pressable ref="scan" @tap="scanTenant" class="w-full bg-theme-primary py-4 rounded-2xl items-center shadow-sm">
                        <native:text font="display" class="text-theme-on-primary text-lg">Scan QR code</native:text>
                    </native:pressable>
                @endif

                <native:column class="w-full gap-4 rounded-3xl bg-theme-surface p-4 shadow-sm border border-theme-outline">
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">Or type the code</native:text>

                    <native:outlined-text-input
                        label="Tenant code"
                        placeholder="ABCD1234"
                        native:model="typedCode"
                        class="w-full"
                    />

                    <native:pressable ref="save-code" @tap="saveTypedCode" class="w-full bg-theme-on-background py-4 rounded-2xl items-center">
                        <native:text font="display" class="text-theme-background text-lg">Save code</native:text>
                    </native:pressable>
                </native:column>

                @if ($replacingTenant && $tenantCode)
                    <native:pressable ref="cancel-tenant" @tap="cancelReplaceTenant" class="items-center py-3">
                        <native:text font="display" class="text-theme-on-surface-variant">Cancel</native:text>
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
            $phaseLabel = match ($phase) {
                'recording' => 'Listening',
                'paused' => 'Paused',
                default => 'Ready',
            };
        @endphp

        <native:column class="w-full flex-1 px-5 pt-3 pb-4 gap-3">
            <native:row class="w-full items-center gap-3">
                <native:image src="brand/mark.png" alt="" class="w-11 h-11" />
                <native:column class="flex-1 gap-0.5">
                    <native:text font="display" class="text-xl tracking-tight text-theme-on-background">{{ $stableName ?: 'Stable Mixer' }}</native:text>
                    <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">{{ $tenantCode }}</native:text>
                </native:column>
                <native:pressable ref="change-tenant" @tap="beginReplaceTenant" class="px-4 py-2 rounded-full bg-theme-surface border border-theme-outline">
                    <native:text font="display" class="text-theme-on-surface">Change</native:text>
                </native:pressable>
            </native:row>

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
                        <native:text font="display" class="text-theme-on-surface text-lg">Stop</native:text>
                    </native:pressable>
                @endif

                @if ($notice)
                    <native:text class="text-theme-on-surface text-center">{{ $notice }}</native:text>
                @endif
            </native:column>

            <native:row class="w-full items-center justify-between">
                <native:text font="mono" class="text-xs uppercase tracking-widest text-theme-on-surface-variant">On this phone</native:text>
                <native:column class="rounded-full bg-theme-surface px-3 py-1 border border-theme-outline">
                    <native:text font="mono" class="text-xs text-theme-on-surface">{{ $pendingCount }} pending sync</native:text>
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
                                            <native:text font="display" class="text-theme-on-primary">Retry</native:text>
                                        </native:pressable>
                                    @endif
                                    @if ($confirmingDelete === $row['id'])
                                        <native:pressable @tap="deleteRecording('{{ $row['id'] }}')" class="px-3 py-2 rounded-full bg-theme-destructive">
                                            <native:text font="display" class="text-theme-on-destructive">Confirm</native:text>
                                        </native:pressable>
                                        <native:pressable @tap="keepRecording" class="px-3 py-2 rounded-full bg-theme-surface-variant">
                                            <native:text font="display" class="text-theme-on-surface">Keep</native:text>
                                        </native:pressable>
                                    @else
                                        <native:pressable @tap="deleteRecording('{{ $row['id'] }}')" class="px-2 py-1">
                                            <native:text class="text-theme-on-surface-variant">Delete</native:text>
                                        </native:pressable>
                                    @endif
                                </native:row>
                            </native:row>
                        </native:column>
                    @empty
                        <native:column class="w-full rounded-2xl bg-theme-surface px-4 py-5 border border-theme-outline">
                            <native:text class="text-theme-on-surface-variant">Nothing recorded yet.</native:text>
                        </native:column>
                    @endforelse
                </native:column>
            </native:scroll-view>
        </native:column>
    @endif
</native:column>
