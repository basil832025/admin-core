<style>
    .tox.tox-tinymce:not(.tox-fullscreen) {
        min-height: 240px !important;
    }

    .tox.tox-tinymce:not(.tox-fullscreen) .tox-editor-container,
    .tox.tox-tinymce:not(.tox-fullscreen) .tox-sidebar-wrap {
        min-height: 190px !important;
    }

    .tox.tox-tinymce:not(.tox-fullscreen) .tox-edit-area,
    .tox.tox-tinymce:not(.tox-fullscreen) iframe {
        min-height: 160px !important;
    }

    html.tox-fullscreen,
    body.tox-fullscreen {
        height: 100%;
        overflow: hidden !important;
    }

    .tox.tox-tinymce.tox-fullscreen {
        inset: 0 !important;
        width: 100vw !important;
        height: 100dvh !important;
        max-width: none !important;
        max-height: none !important;
        z-index: 99999 !important;
    }

    .tox.tox-tinymce.tox-fullscreen .tox-editor-container {
        height: 100% !important;
    }

    .tox.tox-tinymce.tox-fullscreen .tox-sidebar-wrap {
        flex: 1 1 auto !important;
        min-height: 0 !important;
    }

    .tox.tox-tinymce.tox-fullscreen iframe {
        height: 100% !important;
    }

    .tox-fullscreen .tox.tox-tinymce-aux,
    .tox-fullscreen ~ .tox.tox-tinymce-aux {
        z-index: 100000 !important;
    }
</style>

<script>
    (() => {
        let resizeState = null;

        const editorFromHandle = (target) => {
            const handle = target.closest?.('.tox-statusbar__resize-handle');

            return handle?.closest?.('.tox.tox-tinymce:not(.tox-fullscreen)') ?? null;
        };

        const applyEditorHeight = (editor, height) => {
            const nextHeight = Math.max(240, Math.round(height));

            editor.style.height = `${nextHeight}px`;
            editor.style.minHeight = '240px';
            editor.style.maxHeight = 'none';

            editor.querySelector('.tox-editor-container')?.style.setProperty('height', '100%', 'important');
            editor.querySelector('.tox-sidebar-wrap')?.style.setProperty('min-height', '0', 'important');
        };

        const finishResize = () => {
            if (!resizeState) {
                return;
            }

            document.removeEventListener('pointermove', onPointerMove, true);
            document.removeEventListener('pointerup', finishResize, true);
            document.removeEventListener('pointercancel', finishResize, true);
            document.body.style.cursor = resizeState.previousCursor;
            document.body.style.userSelect = resizeState.previousUserSelect;
            resizeState = null;
        };

        const onPointerMove = (event) => {
            if (!resizeState) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            applyEditorHeight(
                resizeState.editor,
                resizeState.startHeight + event.clientY - resizeState.startY,
            );
        };

        document.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }

            const editor = editorFromHandle(event.target);

            if (!editor) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            resizeState = {
                editor,
                startY: event.clientY,
                startHeight: editor.getBoundingClientRect().height,
                previousCursor: document.body.style.cursor,
                previousUserSelect: document.body.style.userSelect,
            };

            document.body.style.cursor = 'ns-resize';
            document.body.style.userSelect = 'none';

            document.addEventListener('pointermove', onPointerMove, true);
            document.addEventListener('pointerup', finishResize, true);
            document.addEventListener('pointercancel', finishResize, true);
        }, true);
    })();
</script>
