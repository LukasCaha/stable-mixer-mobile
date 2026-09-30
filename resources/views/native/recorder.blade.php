<native:column class="w-full h-full bg-white px-6 py-8 gap-5">
    @if ($tenantCode === null || $replacingTenant)
        <native:text class="text-slate-900 text-3xl font-bold">Stable Mixer</native:text>
        <native:text class="text-slate-700">
            Use the demo code to try recording, scan a tenant QR code, or type 8 letters or numbers.
        </native:text>

        @if ($notice)
            <native:text class="text-red-700">{{ $notice }}</native:text>
        @endif

        <native:pressable ref="demo" @tap="useDemoTenant" class="w-full bg-red-600 py-4 rounded-2xl items-center">
            <native:text class="text-white text-lg font-bold">Use demo code</native:text>
        </native:pressable>

        @if ($this->scannerAvailable())
            <native:pressable ref="scan" @tap="scanTenant" class="w-full bg-slate-200 py-4 rounded-2xl items-center">
                <native:text class="text-slate-900 text-lg font-bold">Scan QR code</native:text>
            </native:pressable>
        @endif

        <native:outlined-text-input
            label="Tenant code"
            placeholder="ABCD1234"
            native:model="typedCode"
            class="w-full"
        />

        <native:pressable ref="save-code" @tap="saveTypedCode" class="w-full bg-slate-900 py-4 rounded-2xl items-center">
            <native:text class="text-white font-bold">Save code</native:text>
        </native:pressable>

        @if ($replacingTenant && $tenantCode)
            <native:pressable ref="cancel-tenant" @tap="cancelReplaceTenant" class="items-center py-2">
                <native:text class="text-slate-700">Cancel</native:text>
            </native:pressable>
        @endif
    @else
        <native:row class="w-full items-center justify-between">
            <native:column>
                <native:text class="text-slate-900 text-2xl font-bold">Stable Mixer</native:text>
                <native:text class="text-slate-700">{{ $tenantCode }}</native:text>
            </native:column>
            <native:pressable ref="change-tenant" @tap="beginReplaceTenant" class="px-3 py-2">
                <native:text class="text-slate-900">Change</native:text>
            </native:pressable>
        </native:row>

        <native:column class="w-full items-center gap-4 py-6">
            <native:pressable
                ref="record"
                @tap="cycleRecording"
                class="w-56 h-56 rounded-full bg-red-600 items-center justify-center"
            >
                <native:text class="text-white text-3xl font-bold">{{ $this->recordLabel() }}</native:text>
            </native:pressable>

            @if ($phase !== 'idle')
                <native:pressable ref="stop" @tap="stopRecording" class="px-10 py-3 rounded-full bg-slate-900">
                    <native:text class="text-white text-lg">Stop</native:text>
                </native:pressable>
            @endif

            @if ($notice)
                <native:text class="text-slate-900 text-center">{{ $notice }}</native:text>
            @endif

        </native:column>

        <native:scroll-view class="w-full flex-1">
            <native:column class="w-full gap-4">
                <native:row class="w-full items-center justify-between">
                    <native:text class="text-slate-900">On this phone</native:text>
                    <native:text class="text-slate-500">{{ $pendingCount }} pending sync</native:text>
                </native:row>

                @forelse ($recent as $row)
                    <native:column native:key="rec-{{ $row['id'] }}" class="w-full gap-1">
                        <native:row class="w-full items-center justify-between">
                            <native:text class="text-slate-900">{{ $row['when'] }}</native:text>
                            <native:text class="text-slate-900">{{ $row['duration'] }}</native:text>
                        </native:row>
                        <native:row class="w-full items-center justify-between">
                            <native:text class="text-slate-500">{{ $row['label'] }} · {{ $row['size'] }}</native:text>
                            <native:row class="gap-4">
                                @if ($row['status'] === 'failed')
                                    <native:pressable @tap="retryRecording('{{ $row['id'] }}')">
                                        <native:text class="text-slate-900">Retry</native:text>
                                    </native:pressable>
                                @endif
                                @if ($confirmingDelete === $row['id'])
                                    <native:pressable @tap="deleteRecording('{{ $row['id'] }}')">
                                        <native:text class="text-red-700">Confirm</native:text>
                                    </native:pressable>
                                    <native:pressable @tap="keepRecording">
                                        <native:text class="text-slate-500">Keep</native:text>
                                    </native:pressable>
                                @else
                                    <native:pressable @tap="deleteRecording('{{ $row['id'] }}')">
                                        <native:text class="text-slate-500">Delete</native:text>
                                    </native:pressable>
                                @endif
                            </native:row>
                        </native:row>
                    </native:column>
                @empty
                    <native:text class="text-slate-500">Nothing recorded yet.</native:text>
                @endforelse
            </native:column>
        </native:scroll-view>
    @endif
</native:column>
