<div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
        <button type="button" class="fi-btn fi-btn-size-sm" data-ptd-action="table">Таблица</button>
        <button type="button" class="fi-btn fi-btn-size-sm" data-ptd-action="loop">Цикл datasets</button>
        <button type="button" class="fi-btn fi-btn-size-sm" data-ptd-action="if">Условие if</button>
        <button type="button" class="fi-btn fi-btn-size-sm" data-ptd-action="var">Переменная</button>
    </div>

    <div id="print-template-designer-canvas" style="border:1px solid #d1d5db;border-radius:8px;min-height:520px;"></div>

    <p style="font-size:12px;color:#64748b;margin-top:8px;">
        Visual-режим записывает результат в поле <strong>HTML шаблон (Twig)</strong>. Для точной правки Twig используйте code-режим.
    </p>

    @once
        <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.9/dist/css/grapes.min.css">
        <script src="https://unpkg.com/grapesjs@0.21.9/dist/grapes.min.js"></script>
    @endonce

    @verbatim
    <script>
        (() => {
            const CANVAS_ID = 'print-template-designer-canvas';
            const ROOT = document.getElementById(CANVAS_ID);

            if (!ROOT || ROOT.dataset.ptdInit === '1') {
                return;
            }

            const boot = () => {
                const textarea = document.getElementById('print-template-body-editor');
                if (!textarea || typeof window.grapesjs === 'undefined') {
                    setTimeout(boot, 250);
                    return;
                }

                ROOT.dataset.ptdInit = '1';

                const editor = window.grapesjs.init({
                    container: '#' + CANVAS_ID,
                    fromElement: false,
                    height: '520px',
                    storageManager: false,
                    panels: { defaults: [] },
                    blockManager: {
                        appendTo: null,
                    },
                    components: textarea.value || '<div style="padding:8px;">Новый отчет</div>',
                });

                editor.BlockManager.add('ptd-text', {
                    label: 'Текст',
                    content: '<div style="padding:4px 0;">Текстовый блок</div>',
                });

                editor.BlockManager.add('ptd-table', {
                    label: 'Таблица',
                    content: '<table style="width:100%;border-collapse:collapse;font-size:10pt;"><thead><tr><th style="border:1px solid #d1d5db;padding:4px;">Колонка</th><th style="border:1px solid #d1d5db;padding:4px;">Значение</th></tr></thead><tbody><tr><td style="border:1px solid #d1d5db;padding:4px;">Поле</td><td style="border:1px solid #d1d5db;padding:4px;">{{ params.value|default("-") }}</td></tr></tbody></table>',
                });

                editor.on('update', () => {
                    const html = editor.getHtml();
                    const css = editor.getCss();
                    textarea.value = css ? '<style>' + css + '</style>\n' + html : html;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true }));
                });

                const insertIntoTemplate = (chunk) => {
                    const current = textarea.value || '';
                    const next = (current.trim() ? current.trim() + '\n' : '') + chunk + '\n';
                    textarea.value = next;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.dispatchEvent(new Event('change', { bubbles: true }));
                    editor.setComponents(next);
                };

                ROOT.parentElement?.querySelectorAll('[data-ptd-action]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const action = btn.getAttribute('data-ptd-action');

                        if (action === 'table') {
                            insertIntoTemplate('<table style="width:100%;border-collapse:collapse;font-size:10pt;">\n<thead><tr><th style="border:1px solid #d1d5db;padding:4px;">#</th><th style="border:1px solid #d1d5db;padding:4px;">Назва</th><th style="border:1px solid #d1d5db;padding:4px;">Сума</th></tr></thead>\n<tbody>\n{% for row in datasets.items %}\n<tr><td style="border:1px solid #d1d5db;padding:4px;">{{ loop.index }}</td><td style="border:1px solid #d1d5db;padding:4px;">{{ row.name|default("-") }}</td><td style="border:1px solid #d1d5db;padding:4px;text-align:right;">{{ row.total|default(0)|number_format(2, ".", " ") }}</td></tr>\n{% endfor %}\n</tbody>\n</table>');
                            return;
                        }

                        if (action === 'loop') {
                            insertIntoTemplate('{% for row in datasets.items %}\n<div>{{ row.name|default("-") }}</div>\n{% endfor %}');
                            return;
                        }

                        if (action === 'if') {
                            insertIntoTemplate('{% if params.date_from is defined and params.date_from %}\n<div>Дата з: {{ params.date_from }}</div>\n{% endif %}');
                            return;
                        }

                        insertIntoTemplate('{{ params.value|default("-") }}');
                    });
                });
            };

            boot();
        })();
    </script>
    @endverbatim
</div>
