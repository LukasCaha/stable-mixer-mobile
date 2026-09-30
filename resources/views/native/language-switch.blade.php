<native:row class="gap-2">
    <native:pressable
        ref="lang-en"
        @tap="switchUiLanguage('en')"
        class="px-3 py-2 rounded-full {{ $uiLanguage === 'en' ? 'bg-theme-primary' : 'bg-theme-surface border border-theme-outline' }}"
    >
        <native:text font="display" class="{{ $uiLanguage === 'en' ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">EN</native:text>
    </native:pressable>
    <native:pressable
        ref="lang-cs"
        @tap="switchUiLanguage('cs')"
        class="px-3 py-2 rounded-full {{ $uiLanguage === 'cs' ? 'bg-theme-primary' : 'bg-theme-surface border border-theme-outline' }}"
    >
        <native:text font="display" class="{{ $uiLanguage === 'cs' ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">CZ</native:text>
    </native:pressable>
</native:row>
