<?php
// app/Filament/Resources/LocationResource/RelationManagers/ReviewsRelationManager.php
namespace App\Filament\Clusters\Reference\Resources\LocationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';
    protected static ?string $title = 'Отзывы этой точки';
    protected static ?string $recordTitleAttribute = 'author_name';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('author_name')->label('Автор')->required(),
            Forms\Components\TextInput::make('email')->label('Email')->required(),
            Forms\Components\FileUpload::make('author_avatar')
                ->label('Аватар')->directory('reviews')->image()->imageEditor(),
            Forms\Components\Select::make('rating')->label('Оценка')->options([1=>1,2=>2,3=>3,4=>4,5=>5])->default(5)->required(),
            Forms\Components\Textarea::make('text')->label('Текст')->rows(5)->required(),
            DatePicker::make('posted_at')
                ->label('Дата відгуку')
               // ->native(false)          // красивый календарь; можно убрать, если нужен нативный
                ->displayFormat('d.m.Y') // как показывать в форме
                ->format('Y-m-d')        // как сохранять в БД
                ->default(today())
                ->required(),
            Forms\Components\Toggle::make('is_active')->label('Показывать')->default(true),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\ImageColumn::make('author_avatar')->label(''),
            Tables\Columns\TextColumn::make('author_name')->label('Автор')->searchable(),
            Tables\Columns\TextColumn::make('email')->label('Email')->searchable(),
            Tables\Columns\TextColumn::make('rating')->label('★')->sortable(),
            Tables\Columns\IconColumn::make('is_active')->boolean()->label('Вкл'),
            TextColumn::make('posted_at')
                ->label('Дата')
                ->date('d.m.Y')
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y')->label('Дата'),
        ])->headerActions([Tables\Actions\CreateAction::make()])
            ->defaultSort('posted_at', 'desc')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
