<?php

if (! function_exists('front_view')) {
    function front_view(string $view, ?string $theme = null): string
    {
        $configuredTheme = trim((string) ($theme ?? config('project.theme', '3piroga')));
        $view = ltrim($view, '.');

        $themes = collect([$configuredTheme, '3piroga'])
            ->filter()
            ->unique()
            ->values();

        foreach ($themes as $candidateTheme) {
            $namespacedView = 'front.' . $candidateTheme . '::' . $view;
            if (view()->exists($namespacedView)) {
                return $namespacedView;
            }

            $themedView = 'front.' . $candidateTheme . '.' . $view;
            if (view()->exists($themedView)) {
                return $themedView;
            }
        }

        return $view;
    }
}