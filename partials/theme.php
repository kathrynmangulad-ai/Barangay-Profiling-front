<?php
/* ---------------------------------------------------------------------------
 * Themes (same set as GradeMaster, plus the original Barangay Teal).
 *
 * The colours themselves live in assets/css/style.css: :root is Barangay Teal
 * (the base, applied when <html> has no data-theme) and every other theme is
 * an html[data-theme="<id>"] block. This file only knows the list (for the
 * picker) and boots the saved choice. With nothing saved the app opens in
 * Mist Blue, GradeMaster's default.
 *
 * The choice is stored in localStorage, like GradeMaster:
 *   bms.theme            the browser's last theme (what the sign-in page shows)
 *   bms.theme:<userId>   each account's own choice, so people sharing a PC
 *                        each get their theme back when they sign in
 * ------------------------------------------------------------------------- */

if (!function_exists('bms_themes')) {
    /** id => [label, swatch [page, card, sidebar]]. The first entry is the default. */
    function bms_themes(): array
    {
        return [
            'mist'     => ['label' => 'Mist Blue',     'swatch' => ['#E5EBF4', '#F0F4F9', '#192C48']],
            'slate'    => ['label' => 'Slate Grey',    'swatch' => ['#E7ECF2', '#F1F4F7', '#1C2533']],
            'paper'    => ['label' => 'Warm Paper',    'swatch' => ['#F0ECE5', '#F9F6F2', '#2F241D']],
            'sage'     => ['label' => 'Soft Sage',     'swatch' => ['#E6EDE9', '#F2F6F4', '#173028']],
            'lavender' => ['label' => 'Lavender',      'swatch' => ['#EEEAF4', '#F8F5FB', '#281B34']],
            'midnight' => ['label' => 'Midnight Blue', 'swatch' => ['#0A111E', '#111827', '#101D32']],
            'graphite' => ['label' => 'Graphite',      'swatch' => ['#0F1011', '#141516', '#08090A']],
            'teal'     => ['label' => 'Barangay Teal', 'swatch' => ['#F5F7FA', '#FFFFFF', '#063C46']],
        ];
    }

    /**
     * Inline <script> for <head>: applies the saved theme before the page
     * paints, so it never flashes the default theme first. Pass the signed-in
     * user's id (0 on the sign-in pages) to prefer their own saved choice.
     */
    function bms_theme_boot_script(int $user_id = 0): string
    {
        $ids = array_keys(bms_themes());
        $default = $ids[0];
        // 'teal' is the stylesheet's base palette: it is shown by leaving
        // data-theme off, so it is the one id that never becomes an attribute.
        $js = '(function(){var k="bms.theme",u=' . $user_id . ',t=null,ok=' . json_encode($ids) . ';'
            . 'try{if(u){t=localStorage.getItem(k+":"+u);}if(!t){t=localStorage.getItem(k);}}catch(e){}'
            . 'if(!t||ok.indexOf(t)===-1){t=' . json_encode($default) . ';}'
            . 'if(t!=="teal"){document.documentElement.setAttribute("data-theme",t);}})();';
        return '<script>' . $js . '</script>';
    }
}
