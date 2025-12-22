<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ManageBlogComments extends ManageRelatedRecords
{
    protected static string $resource = BlogResource::class;
    protected static ?string $navigationLabel = 'Комментарии блога';
    // Имя связи в модели Blog: comments()
    protected static string $relationship = 'comments';

    // Заголовок страницы
    public function getTitle(): string
    {
        $title = $this->getRecordTitle();
        return "Comments for {$title}";
    }

    // Форма создания/редактирования комментария в модальном окне
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('author_name')
                    ->label('Author Name')
                    ->required(),
                Forms\Components\TextInput::make('author_email')
                    ->label('Author Email')
                    ->email()//->required()
                    ,
                Forms\Components\Textarea::make('content')
                    ->label('Comment')
                    ->rows(4)
                    ->required(),
                Forms\Components\Toggle::make('is_approved')
                    ->label('Approved')
                    ->default(false),
            ])
            ->columns(1);
    }

    // Таблица комментариев с action’ами и headerActions (Create, Edit, Delete)
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('author_name')
            ->columns([
                TextColumn::make('author_name')
                    ->label('Author')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('author_email')
                    ->label('Email')
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Comment')
                    ->limit(50)
                    ->wrap(),
                ToggleColumn::make('is_approved')->label(__('Разрешить')),
              /*  IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean(),*/
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->filters([
                // можно добавить фильтры тут
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
