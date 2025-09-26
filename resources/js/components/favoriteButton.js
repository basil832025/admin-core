export default function registerFavoriteButton(Alpine) {
    Alpine.data('favoriteButton', (opts = {}) => ({
        id: opts.id ?? null,
        active: !!opts.active,
        color: opts.color ?? '#FF7500',
        persist: opts.persist ?? true,
        postUrl: opts.postUrl ?? null,
        csrf: opts.csrf ?? (document.querySelector('meta[name=csrf-token]')?.content || null),
        loading: false,

        init() {
            this.$root.style.setProperty('--fav-color', this.color);
            if (this.persist && this.id != null) {
                const saved = localStorage.getItem(`fav:${this.id}`);
                if (saved === '1' || saved === '0') this.active = saved === '1';
            }
        },

        async toggle() {
            if (this.loading) return;
            this.active = !this.active;

            this.$refs.icon?.classList.add('scale-110');
            setTimeout(() => this.$refs.icon?.classList.remove('scale-110'), 120);

            if (this.persist && this.id != null) {
                localStorage.setItem(`fav:${this.id}`, this.active ? '1' : '0');
            }

            if (this.postUrl && this.id != null) {
                try {
                    this.loading = true;
                    await fetch(this.postUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...(this.csrf ? { 'X-CSRF-TOKEN': this.csrf } : {}),
                        },
                        body: JSON.stringify({ product_id: this.id, favorite: this.active }),
                        credentials: 'same-origin',
                    });
                } catch (e) {
                    this.active = !this.active;
                    if (this.persist) localStorage.setItem(`fav:${this.id}`, this.active ? '1' : '0');
                    console.error('favorite toggle failed', e);
                } finally {
                    this.loading = false;
                }
            }

            this.$dispatch('favorite-changed', { id: this.id, active: this.active });
        },
    }));
}
