<style>
    /* делаем в модалке Curator именно сетку и мелкие карточки */
    .curator-picker-grid{
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)) !important; /* ширина превью */
        gap: .75rem !important;
    }
    .curator-picker-grid > li{
        aspect-ratio: 1 / 1;       /* квадратные ячейки */
    }
    .curator-picker-grid img{
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        max-height: none !important; /* на случай наследованных ограничений */
    }
</style>
