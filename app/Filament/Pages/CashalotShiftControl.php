<?php

namespace App\Filament\Pages;

use App\Models\Shop\Prro;
use App\Models\Shop\CashalotCommandLog;
use App\Services\CashalotApiClient;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CashalotShiftControl extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    /**
     * Form state container.
     * Filament expects fields to live under a state path to avoid creating many public props.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = 'Cashalot: зміна';
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?int $navigationSort = 97;
    protected static string $view = 'filament.pages.cashalot-shift-control';

    public ?string $prro_number = null;
    public ?string $organization_name = null;

    public array $last_response = [];
    public ?string $last_action = null;

    public string $last_response_json = '';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        if (! $user || ! $user instanceof \App\Models\User) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('page_CashalotShiftControl');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $prro = Prro::query()
            ->where('is_active', true)
            ->where('use_for_liqpay', true)
            ->latest('id')
            ->first();

        $this->prro_number = $prro?->prro_number;
        $this->organization_name = $prro?->organization_name;

        $this->form->fill([
            'zrep_auto' => true,
            'visualization' => false,
            'cleanup_remove' => false,
            'service_deposit_sum' => null,
            'service_issue_sum' => null,
            'last_response_json' => '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Активний ПРРО')
                    ->schema([
                        Forms\Components\Placeholder::make('prro_info')
                            ->content(function (): string {
                                $parts = [];
                                $parts[] = 'ПРРО: ' . ($this->prro_number ?: '-');
                                $parts[] = 'Організація: ' . ($this->organization_name ?: '-');
                                return implode(' | ', $parts);
                            }),
                    ]),

                Forms\Components\Section::make('Керування')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('zrep_auto')
                            ->label('Auto Z при закритті')
                            ->default(true),
                        Forms\Components\Toggle::make('visualization')
                            ->label('Visualization (Base64)')
                            ->default(false)
                            ->helperText('Відповідь може бути дуже великою.'),
                        Forms\Components\Toggle::make('cleanup_remove')
                            ->label('Cleanup: Remove=true')
                            ->default(false)
                            ->helperText('Не рекомендується без потреби.'),
                    ]),

                Forms\Components\Section::make('Службові операції')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('service_deposit_sum')
                            ->label('Внесення (сума)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->placeholder('0.00'),
                        Forms\Components\TextInput::make('service_issue_sum')
                            ->label('Інкасація (сума)')
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->placeholder('0.00'),
                    ]),

                Forms\Components\Section::make('Остання відповідь')
                    ->schema([
                        Forms\Components\Placeholder::make('last_action')
                            ->label('Операція')
                            ->content(fn (): string => $this->last_action ?: '-'),
                        Forms\Components\Textarea::make('last_response_json')
                            ->label('JSON')
                            ->rows(14)
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    protected function getActions(): array
    {
        return [
            Action::make('state')
                ->label('Стан ПРРО')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    $this->call('TransactionsRegistrarState', fn (CashalotApiClient $api) => $api->transactionsRegistrarState());
                }),

            Action::make('openShift')
                ->label('Відкрити зміну')
                ->icon('heroicon-o-play')
                ->color('gray')
                ->action(function (): void {
                    $this->call('OpenShift', fn (CashalotApiClient $api) => $api->openShift());
                }),

            Action::make('closeShift')
                ->label('Закрити зміну')
                ->icon('heroicon-o-stop')
                ->color('warning')
                ->action(function (): void {
                    $state = $this->form->getState();
                    $zrepAuto = (bool) Arr::get($state, 'zrep_auto', true);
                    $vis = (bool) Arr::get($state, 'visualization', false);
                    $this->call('CloseShift', fn (CashalotApiClient $api) => $api->closeShift($zrepAuto, $vis));
                }),

            Action::make('registerZ')
                ->label('Z-звіт')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->action(function (): void {
                    $state = $this->form->getState();
                    $vis = (bool) Arr::get($state, 'visualization', false);
                    $this->call('RegisterZRep', fn (CashalotApiClient $api) => $api->registerZRep(null, $vis));
                }),

            Action::make('cleanup')
                ->label('Cleanup')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $state = $this->form->getState();
                    $remove = (bool) Arr::get($state, 'cleanup_remove', false);
                    $vis = (bool) Arr::get($state, 'visualization', false);
                    $this->call('Cleanup', fn (CashalotApiClient $api) => $api->cleanup($remove, $vis));
                }),

            Action::make('serviceDeposit')
                ->label('Внесення')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function (): void {
                    $state = $this->form->getState();
                    $sum = (float) (Arr::get($state, 'service_deposit_sum') ?? 0);
                    if ($sum <= 0) {
                        Notification::make()
                            ->title('Cashalot: помилка')
                            ->body('Вкажіть суму внесення')
                            ->danger()
                            ->send();
                        return;
                    }

                    $vis = (bool) Arr::get($state, 'visualization', false);
                    $check = $this->buildServiceCheck('ServiceDeposit', $sum);
                    $this->call('RegisterCheck:ServiceDeposit', fn (CashalotApiClient $api) => $api->registerCheck($check, [
                        'auto_open_shift' => false,
                        'visualization' => $vis,
                    ]));
                }),

            Action::make('serviceIssue')
                ->label('Інкасація')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->action(function (): void {
                    $state = $this->form->getState();
                    $sum = (float) (Arr::get($state, 'service_issue_sum') ?? 0);
                    if ($sum <= 0) {
                        Notification::make()
                            ->title('Cashalot: помилка')
                            ->body('Вкажіть суму інкасації')
                            ->danger()
                            ->send();
                        return;
                    }

                    $vis = (bool) Arr::get($state, 'visualization', false);
                    $check = $this->buildServiceCheck('ServiceIssue', $sum);
                    $this->call('RegisterCheck:ServiceIssue', fn (CashalotApiClient $api) => $api->registerCheck($check, [
                        'auto_open_shift' => false,
                        'visualization' => $vis,
                    ]));
                }),
        ];
    }

    private function call(string $action, callable $runner): void
    {
        $this->last_action = $action;

        try {
            /** @var CashalotApiClient $api */
            $api = app(CashalotApiClient::class);
            $response = $runner($api);
            $this->last_response = is_array($response) ? $response : ['response' => $response];
            $this->last_response_json = json_encode($this->last_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '';
            $this->data['last_response_json'] = $this->last_response_json;

            // Build request payload for logging.
            $requestPayload = $this->buildRequestPayloadForAction($action, $api);

            $errorCode = mb_strtolower(trim((string) ($this->last_response['ErrorCode'] ?? '')));
            if ($errorCode !== '' && $errorCode !== 'ok') {
                $this->logCommand($action, $requestPayload, $this->last_response);
                Notification::make()
                    ->title('Cashalot: помилка')
                    ->body((string) ($this->last_response['ErrorMessage'] ?? $this->last_response['ErrorCode'] ?? 'Unknown error'))
                    ->danger()
                    ->send();
                return;
            }

            $this->logCommand($action, $requestPayload, $this->last_response);

            Notification::make()
                ->title('Cashalot: виконано')
                ->body($action)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->last_response = [
                'ErrorCode' => 'EXCEPTION',
                'ErrorMessage' => $e->getMessage(),
            ];
            $this->last_response_json = json_encode($this->last_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '';
            $this->data['last_response_json'] = $this->last_response_json;

            $this->logCommand($action, $this->buildRequestPayloadForAction($action, app(CashalotApiClient::class)), $this->last_response);

            Notification::make()
                ->title('Cashalot: exception')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function buildRequestPayloadForAction(string $action, CashalotApiClient $api): array
    {
        $state = $this->form->getState();

        $cmd = match ($action) {
            'TransactionsRegistrarState' => [
                'Command' => 'TransactionsRegistrarState',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
            ],
            'OpenShift' => [
                'Command' => 'OpenShift',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
            ],
            'CloseShift' => [
                'Command' => 'CloseShift',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
                'ZRepAuto' => (bool) Arr::get($state, 'zrep_auto', true),
                'Visualization' => (bool) Arr::get($state, 'visualization', false),
            ],
            'RegisterZRep' => [
                'Command' => 'RegisterZRep',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
                'ZRep' => null,
                'Visualization' => (bool) Arr::get($state, 'visualization', false),
            ],
            'Cleanup' => [
                'Command' => 'Cleanup',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
                'Remove' => (bool) Arr::get($state, 'cleanup_remove', false),
                'Visualization' => (bool) Arr::get($state, 'visualization', false),
            ],
            'RegisterCheck:ServiceDeposit' => [
                'Command' => 'RegisterCheck',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
                'Check' => $this->buildServiceCheck('ServiceDeposit', (float) (Arr::get($state, 'service_deposit_sum') ?? 0)),
                'AutoOpenShift' => false,
                'Visualization' => (bool) Arr::get($state, 'visualization', false),
                'VisAsHtml' => false,
                'GetQrCode' => true,
            ],
            'RegisterCheck:ServiceIssue' => [
                'Command' => 'RegisterCheck',
                'UID' => (string) Str::uuid(),
                'NumFiscal' => (string) (app(\App\Services\PrroConfigService::class)->getForCashalot()['numfiscal'] ?? ''),
                'Check' => $this->buildServiceCheck('ServiceIssue', (float) (Arr::get($state, 'service_issue_sum') ?? 0)),
                'AutoOpenShift' => false,
                'Visualization' => (bool) Arr::get($state, 'visualization', false),
                'VisAsHtml' => false,
                'GetQrCode' => true,
            ],
            default => ['Command' => $action],
        };

        return $api->buildRequestPayload($cmd);
    }

    /**
     * Build Cashalot RegisterCheck payload for service cash operations.
     *
     * @return array<string, mixed>
     */
    private function buildServiceCheck(string $docSubType, float $sum): array
    {
        $sum = round(max(0, $sum), 2);

        return [
            'CHECKHEAD' => [
                'DOCTYPE' => 'SaleGoods',
                'DOCSUBTYPE' => $docSubType,
                'TESTING' => false,
                'COMMENT' => $docSubType === 'ServiceDeposit'
                    ? 'Службове внесення (адмінка)'
                    : 'Інкасація (адмінка)',
            ],
            'CHECKTOTAL' => [
                'SUM' => $sum,
            ],
        ];
    }

    private function logCommand(string $action, array $requestPayload, array $responsePayload): void
    {
        $errorCode = (string) ($responsePayload['ErrorCode'] ?? null);
        $errorMessage = (string) ($responsePayload['ErrorMessage'] ?? null);
        $status = 'unknown';
        if ($errorCode !== '') {
            $status = mb_strtolower(trim($errorCode)) === 'ok' ? 'success' : 'failed';
        }

        $prroNumFiscal = (string) ($requestPayload['NumFiscal'] ?? '');
        $resultNumFiscal = (string) ($responsePayload['NumFiscal'] ?? '');
        $shiftId = (string) ($responsePayload['ShiftId'] ?? '');

        $adminUserId = auth('admin')->id();

        CashalotCommandLog::create([
            'admin_user_id' => $adminUserId ? (int) $adminUserId : null,
            'command' => $action,
            'prro_num_fiscal' => $prroNumFiscal !== '' ? $prroNumFiscal : null,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'status' => $status,
            'error_code' => $errorCode !== '' ? $errorCode : null,
            'error_message' => $errorMessage !== '' ? $errorMessage : null,
            'result_num_fiscal' => $resultNumFiscal !== '' ? $resultNumFiscal : null,
            'shift_id' => $shiftId !== '' ? $shiftId : null,
        ]);

        Log::info('Cashalot command', [
            'action' => $action,
            'admin_user_id' => $adminUserId,
            'prro_num_fiscal' => $prroNumFiscal,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'result_num_fiscal' => $resultNumFiscal,
            'shift_id' => $shiftId,
        ]);
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

}
