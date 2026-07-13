<?php

if (! function_exists('front_view')) {
    function front_view(string $view, ?string $theme = null): string
    {
        $theme ??= (string) config('project.theme', '3piroga');
        $theme = trim($theme);
        $view = ltrim($view, '.');

        if ($theme === '') {
            return $view;
        }

        $namespacedView = 'front.' . $theme . '::' . $view;
        if (view()->exists($namespacedView)) {
            return $namespacedView;
        }

        $themedView = 'front.' . $theme . '.' . $view;

        return view()->exists($themedView) ? $themedView : $view;
    }
}
