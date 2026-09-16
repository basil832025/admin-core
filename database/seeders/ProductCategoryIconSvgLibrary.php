<?php

namespace Database\Seeders;

final class ProductCategoryIconSvgLibrary
{
    /** SVG-файлы, необходимые ProductCategoryIconsSeeder на чистом окружении. */
    public const ICONS = [
        'pies' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14c0-5 4-9 9-9s9 4 9 9"/><path d="M3 14h18v3c0 1.7-1.3 3-3 3H6c-1.7 0-3-1.3-3-3z"/><path d="m7 10 3 3m0-6 4 6m1-5 2 5"/></svg>',
        'cheese' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-6 9 6v9H3z"/><path d="M3 10h18M12 4l3 6"/><circle cx="8" cy="15" r="1.5"/><circle cx="16.5" cy="14" r="1"/></svg>',
        'meat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c1.2 5.6-3.4 10-8.5 10C6.8 20 3 17.2 3 13.5 3 10 6.2 7 10 7c2 0 2.4-3 5.7-3 2.8 0 4.3 2.6 4.3 6Z"/><path d="M7 14c1.8-2.8 5.2-4.8 9-5"/><circle cx="15.8" cy="8" r="1.6"/></svg>',
        'vegan' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 4C11 4 5 8 5 15c0 3 2 5 5 5 7 0 10-7 10-16Z"/><path d="M4 21c3-6 7-9 13-13"/></svg>',
        'sweets' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 8-5-2 2 5-2 5 5-2m8-6 5-2-2 5 2 5-5-2"/><rect x="8" y="7" width="8" height="10" rx="3"/><path d="m10 10 4 4m0-4-4 4"/></svg>',
        'traditional' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 9h14l-1 11H6z"/><path d="M8 9c0-3 1.5-5 4-5s4 2 4 5M4 9h16"/><path d="M9 14h6"/></svg>',
        'combo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="14" rx="2"/><path d="M8 6V4h8v2M3 11h18M8 15h3m2 0h3"/></svg>',
        'tasting-set' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="9" r="3"/><circle cx="17" cy="9" r="3"/><circle cx="12" cy="16" r="3"/><path d="M4 21h16"/></svg>',
        'drinks' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h9l-1 16H8zM8 9h7"/><path d="m14 4 3-2m-5 7 4-4"/></svg>',
        'sauces' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6v4l2 3v9c0 1.1-.9 2-2 2H9c-1.1 0-2-.9-2-2v-9l2-3z"/><path d="M9 7h6M7 12h10M10 16h4"/></svg>',
        'cakes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11h16v9H4zM3 20h18"/><path d="M5 11c1-4 3-5 7-5s6 1 7 5M12 6V3"/><path d="M12 3c1-1 2-.5 2 .5S13 5 12 4.5"/></svg>',
        'dishes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13h16c0 4-3 7-8 7s-8-3-8-7Z"/><path d="M2 13h20M7 10c0-2 2-4 5-4s5 2 5 4M12 6V4"/></svg>',
    ];
}
