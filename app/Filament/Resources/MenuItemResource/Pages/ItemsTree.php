<?php

    namespace App\Filament\Resources\MenuItemResource\Pages;

    use App\Filament\Resources\MenuItemResource;
    use App\Models\Menu;
    use App\Models\MenuItem;
    use Filament\Pages\Actions\CreateAction;
    use Filament\Pages\SubNavigationPosition;
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Database\Eloquent\Model;
    use SolutionForest\FilamentTree\Resources\Pages\TreePage as BasePage;

    class ItemsTree extends BasePage
    {
        protected static string $resource = MenuItemResource::class;

        /** Модель дерева (как в твоём рабочем примере) */
        protected static string $model = MenuItem::class;

        /** --- ВАЖНО: корень и имена колонок --- */
        protected static int|string|null $rootParentId = -1;
        protected static string $parentColumnName = 'parent_id';
        protected static string $orderColumnName  = 'sort';



        /** Если твоя сборка читает методы — отдаём те же значения методами */
        protected function getRootParentId(): int|string|null { return -1; }
        protected function getParentColumnName(): string      { return 'parent_id'; }
        protected function getOrderColumnName(): string       { return 'sort'; }

        /** Красивый заголовок у страницы */
        protected bool $enableTreeTitle = true;

        /** Верхняя саб-навигация (как в примере) */
        protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

        /** Текущее меню (получаем из роут-параметра) */
        public Menu $menu;

        /** Вместо сессии — просто ID и ленивая загрузка модели */
        public int $menuId;


     /*   public function mount(int|string|array|null $menu = null): void
        {
            $this->menuId = (int) (
            $menu instanceof Menu
                ? $menu->getKey()
                : (is_array($menu) ? ($menu['menu'] ?? reset($menu)) : $menu)
            );

            // опционально — подгружаем саму модель (нужно для заголовка)
            $this->menu = Menu::findOrFail($this->menuId);
        }*/
        public function mount($menu = null): void
        {
            // 1) Попытка №1 — то, что пришло аргументом (мог быть Menu, массив или скаляр)
            $id = null;

            if ($menu instanceof \App\Models\Menu) {
                $id = (int) $menu->getKey();
                $this->menu = $menu; // можно сразу использовать
            } elseif (is_array($menu)) {
                $id = (int) ($menu['id'] ?? $menu['menu'] ?? reset($menu));
            } elseif ($menu !== null && $menu !== '') {
                $id = (int) $menu;
            }

            // 2) Попытка №2 — забираем прямо из роута (самый надежный способ)
            if (!$id) {
                $fromRoute = request()->route('menu') ?? request()->route('record') ?? request()->route('id');
                if ($fromRoute instanceof \App\Models\Menu) {
                    $id = (int) $fromRoute->getKey();
                } elseif (is_array($fromRoute)) {
                    $id = (int) ($fromRoute['id'] ?? $fromRoute['menu'] ?? reset($fromRoute));
                } else {
                    $id = (int) $fromRoute;
                }
            }

            // 3) Финальная проверка
            if (!$id) {
                abort(404, 'Menu id is missing'); // чтобы сразу видеть причину
            }

            $this->menuId = $id;

            // 4) Загружаем модель без влияния глобальных скоупов/soft deletes
            $q = \App\Models\Menu::query()->withoutGlobalScopes();
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses(\App\Models\Menu::class), true)) {
                $q->withTrashed();
            }

            $this->menu = $this->menu ?? $q->findOrFail($this->menuId);
           // dd($this->menu,$this->menuId);
        }

        protected function getTreeData(): array
        {
            return MenuItem::query()
                ->where('menu_id', $this->record->id)   // если это дерево пунктов конкретного меню
                ->whereNull('parent_id')
                ->with('childrenRecursive')
                ->orderBy('sort')
                ->get()
                ->map(fn (MenuItem $n) => $this->mapNode($n))
                ->values()
                ->all();
        }

        protected function mapNode(MenuItem $n): array
        {
            return [
                'id'       => $n->id,
                'title'    => (string) $n->label,   // используем поле label из модели
                // любые ваши поля...
                'children' => $n->childrenRecursive
                    ? $n->childrenRecursive->map(fn ($c) => $this->mapNode($c))->values()->all()
                    : [],                           // ← никогда не null
                'actions'  => [],                   // ← тоже массив, если не используете
            ];
        }
        /** H1 */
        private function pickLocalizedText(mixed $raw, string $locale, string $fallback = ''): string
        {
            // JSON string → array
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = $decoded;
                } else {
                    return trim($raw) !== '' ? $raw : $fallback;
                }
            }

            // stdClass → array
            if (is_object($raw)) {
                $raw = (array) $raw;
            }

            if (is_array($raw)) {
                $val = $raw[$locale] ?? reset($raw) ?? null;

                // если по локали снова массив/объект — берём первый скаляр
                if (is_object($val)) $val = (array) $val;
                if (is_array($val))  $val = reset($val);

                return is_scalar($val) && $val !== '' ? (string) $val : $fallback;
            }

            return is_scalar($raw) && $raw !== '' ? (string) $raw : $fallback;
        }

       public function getTitle(): string
        {
            $title = $this->pickLocalizedText($this->menu->title, app()->getLocale(), $this->menu->slug);
            return 'Пункти меню: ' . $title;
        }
        /** Ограничиваем дерево текущим меню */
        protected function getTreeQuery(): \Illuminate\Database\Eloquent\Builder
        {
            $q = \App\Models\MenuItem::query()
                ->where('menu_id', $this->menu->id);

            $countAll   = (clone $q)->count();
            $countRoots = (clone $q)->where('parent_id', -1)->count();


            // для жесткой проверки выведем первые 5 root-записей:
            $roots = (clone $q)->where('parent_id', -1)->orderBy('sort')->orderBy('id')->limit(5)->get(['id','parent_id','label']);

            return $q;
        }

    // ItemsTree.php
        public function getTreeRecordTitle(?Model $record = null): string
        {
            if (! $record) return '';
            $raw = $record->label;
            $loc = app()->getLocale();

            $text = is_array($raw) ? ($raw[$loc] ?? reset($raw) ?? null) : (string) $raw;
            if (is_array($text)) $text = reset($text) ?: null;

            $text = $text ?: ('#'.$record->id);
            $status = $record->is_active ? '✅' : '❌';

            return "{$text} | {$status}";
        }
        protected function getTreeRecordTitleColumnName(): ?string
        {
            return 'label';   // это поле из модели
        }

    // для других версий (старое имя):
        protected function getRecordTitleAttributeName(): string
        {
            return 'label';
        }




        /** Подзаголовок узла (мелким текстом справа) — отдадим slug/тип */
        protected function getTreeRecordSubtitleColumnName(): ?string
        {
            return 'link_type'; // можно 'slug' если есть, я поставил тип ссылки
        }



        /** Кнопки в тулбаре */
        protected function getActions(): array
        {
            return [
                CreateAction::make()->label(__('Створити')), // стандартная "создать"
            ];
        }

        /** Хотим ли показывать блок деталей под узлом */
        protected function hasTreeRecordDetails(): bool
        {
            return true;
        }

        /** Настройка ключей записи — встречается в некоторых минорках */
        protected function getTreeRecordKey(): string       { return 'id'; }
        protected function getTreeRecordParentKey(): string { return 'parent_id'; }
        protected function getTreeRecordOrderKey(): string  { return 'sort'; }

        /** Убираем лишние действия, если не нужны */
        protected function hasDeleteAction(): bool  { return true; }
        protected function hasViewAction(): bool    { return false; }
        protected function hasEditAction(): bool    { return true; }
    }
