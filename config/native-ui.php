<?php

/**
 * Native UI — Theme Tokens
 *
 * Published via `php artisan vendor:publish --tag=native-ui-config`.
 * Edit to customize your app's visual identity in one place.
 *
 * For dynamic per-tenant theming, use Native\Mobile\UI\Theme::merge([...])
 * from a service provider. Runtime merges deep-merge on top of these values.
 *
 * Decision log: /docs/NATIVE-UI-REWRITE-PLAN.md (D — theme layer)
 */

return [

    /*
    |---------------------------------------------------------------------------
    | Theme
    |---------------------------------------------------------------------------
    |
    | Color tokens (open-ended map), 4 radii, 4 font sizes, font family.
    |
    | "on-X" means "color of content placed ON a surface of color X"
    |   — i.e., text/icons on that background.
    |
    | The token map is OPEN-ENDED: add any key your design needs (e.g. a
    | `warning` pair) to both blocks and `bg-theme-warning` /
    | `text-theme-on-warning` / `border-theme-warning` resolve immediately.
    | Theme classes also accept opacity modifiers — `bg-theme-primary/15`
    | is the tonal-fill idiom (the alpha applies to the dark companion
    | too). In PHP (layout chrome builders, dynamic styling) read tokens
    | with the appearance-aware `theme()` helper: `theme('primary')`.
    |
    | Color tokens accept:
    |   - CSS hex: '#B91C1C', '#F00', or with alpha '#8B5CF680' (#RRGGBBAA)
    |   - Tailwind palette names: 'red-300', 'orange-800'
    |   - Opacity modifiers on either: 'red-300/20', '#8B5CF6/50'
    |
    | Dark mode is auto-derived from `light` when `dark` is not set. To opt
    | into explicit dark tokens, fill out the `dark` block.
    |
    | The default pairs meet WCAG AA (4.5:1) — if you customize, keep each
    | `on-*` color at 4.5:1 contrast against its background token.
    |
    */

    'theme' => [

        'light' => [
            // Yard green — filled actions, the idle record control.
            'primary' => '#3E4C3C',
            'on-primary' => '#F6F3EC',

            // Sage — secondary actions and quiet emphasis.
            'secondary' => '#5C6B54',
            'on-secondary' => '#F6F3EC',

            // Surface = cards. Background = the warm paper of the brand site.
            'surface' => '#FFFCF7',
            'on-surface' => '#1C1B18',
            'background' => '#F6F3EC',
            'on-background' => '#1C1B18',

            // Surface variant = filled text fields, muted tonal surfaces.
            // on-surface-variant = muted label/hint text on those surfaces.
            'surface-variant' => '#EFE8DC',
            'on-surface-variant' => '#5C574E',

            // Cream fill so the pairing code field reads as a field on the paper background.
            'input-fill' => '#FFFCF7',
            'on-input' => '#1C1B18',

            // Outline = neutral borders (text fields, dividers, cards).
            // outline-variant = softer edges: hairline dividers, card seams.
            'outline' => '#E6DFD2',
            'outline-variant' => '#E6DFD2',

            // Live recording — the red dot from the brand mark, not a generic alert red.
            'destructive' => '#E23B2F',
            'on-destructive' => '#FFFCF7',

            // Success / "safe to proceed" — confirmations, verified badges.
            'success' => '#3E4C3C',
            'on-success' => '#F6F3EC',

            // Warm brass used on the brand plates.
            'accent' => '#D7C4A4',
            'on-accent' => '#1C1B18',
        ],

        'dark' => [
            // Warm night version of the same paper and green, so dark mode
            // stays a yard app instead of the default teal slate.
            'primary' => '#C5D0BC',
            'on-primary' => '#1C1B18',

            'secondary' => '#8A9A7E',
            'on-secondary' => '#1C1B18',

            'surface' => '#2A2823',
            'on-surface' => '#F6F3EC',
            'background' => '#1C1B18',
            'on-background' => '#F6F3EC',

            'surface-variant' => '#3A3832',
            'on-surface-variant' => '#C9C2B4',

            'input-fill' => '#2A2823',
            'on-input' => '#F6F3EC',

            'outline' => '#4A463E',
            'outline-variant' => '#4A463E',

            'destructive' => '#F07168',
            'on-destructive' => '#1C1B18',

            'success' => '#C5D0BC',
            'on-success' => '#1C1B18',

            'accent' => '#D7C4A4',
            'on-accent' => '#1C1B18',
        ],

        // Corner radii (points / dp). Cards on the brand site sit around 18.
        'radius-sm' => 8,
        'radius-md' => 14,
        'radius-lg' => 18,
        'radius-full' => 9999,

        // Font size scale (points / sp).
        'font-sm' => 14,
        'font-md' => 16,
        'font-lg' => 20,
        'font-xl' => 24,

    ],

    /*
    |---------------------------------------------------------------------------
    | Fonts
    |---------------------------------------------------------------------------
    |
    | Semantic names for bundled fonts (resources/fonts/ file tokens, minus
    | the extension). Use an alias anywhere a font token works — the `font`
    | attribute (`font="accent"`), chrome ->font() builders, or the layout
    | $font property. The `default` alias is the app-wide default font:
    | 'System' resolves to the platform face (San Francisco on iOS, Roboto
    | on Android); set a bundled token to apply it everywhere. Download one
    | with `php artisan native:font Inter --default`. Per-element `font`
    | attributes and font-serif / font-mono classes still win over the default.
    |
    |   'fonts' => [
    |       'default' => 'Inter-Regular',
    |       'accent'  => 'DynaPuff-Regular',
    |   ],
    |
    */

    'fonts' => [
        'default' => 'BricolageGrotesque-Regular',
        'display' => 'BricolageGrotesque-SemiBold',
        'mono' => 'DmMono-Medium',
    ],

];
