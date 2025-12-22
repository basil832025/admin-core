<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogCommentResource\Pages;
use App\Models\BlogComment;
use Filament\Resources\Resource;
//use Filament\Resources\Form;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextInputColumn;

class BlogCommentResource extends Resource
{
    protected static ?string $model = BlogComment::class;

    //  protected static ?string $navigationIcon = 'heroicon-o-chat';
    //   protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    //  protected static ?string $navigationGroup = 'Blog';
    // protected static ?int $navigationSort = 2;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Comment Data')
                    ->schema([
                        Forms\Components\Select::make('blog_id')
                            ->relationship('blog', 'slug')
                            ->label('Blog Post')
                            ->disabled(fn ($livewire) => $livewire instanceof Pages\CreateBlogComment)
                            ->required(),
                        Forms\Components\TextInput::make('author_name')
                            ->label('Author Name')
                            ->required(),
                        Forms\Components\TextInput::make('author_email.')
                            ->label('Author Email')
                            ->email()  //->required()
                            ,
                        Forms\Components\Textarea::make('content')
                            ->label('Content')
                            ->rows(4)
                            ->required(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Reply To')
                            ->relationship('parent', 'author_name')
                            ->nullable(),
                        Forms\Components\Toggle::make('is_approved')
                            ->label('Approved')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author_email')
                    ->label('Email')
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Comment')
                    ->limit(50)
                    ->wrap()
                    ->sortable(false),
                IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\Filter::make('approved')
                    ->query(fn ($query) => $query->where('is_approved', true))
                    ->label('Approved only'),
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

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBlogComments::route('/'),
            'create' => Pages\CreateBlogComment::route('/create'),
            'edit'   => Pages\EditBlogComment::route('/{record}/edit'),
        ];
    }
}
