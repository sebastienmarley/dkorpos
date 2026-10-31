/**
 * Verrouillage de la session après inactivité (ADR 0015).
 *
 * L'inactivité est partagée entre les onglets par le localStorage : travailler dans un onglet garde les autres
 * ouverts, et verrouiller ou déverrouiller un onglet fait de même pour les autres. Le serveur reste la source de vérité :
 * il refuse les requêtes d'une session verrouillée, et l'activité sur une page déjà chargée lui est signalée par un
 * battement de cœur.
 *
 * Le bouton Précédent peut réafficher une page sans la redemander au serveur : wire:navigate la restaure de son cache
 * en réinitialisant ses composants, et le navigateur la sort de son cache (bfcache). Une page restaurée redemande donc
 * l'état du verrou au serveur, et affiche l'écran sans attendre si un onglet a verrouillé la session entre-temps.
 */
const LAST_ACTIVITY_KEY = 'dkor.session-lock.last-activity';
const STATE_KEY = 'dkor.session-lock.state';
const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'wheel', 'scroll'];
const CHECK_INTERVAL_MS = 5_000;
const ACTIVITY_WRITE_INTERVAL_MS = 5_000;
const HEARTBEAT_INTERVAL_MS = 30_000;

function readStorage(key) {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Stockage indisponible (navigation privée) : l'onglet se verrouille seul, le serveur reste la protection.
    }
}

document.addEventListener('livewire:init', () => {
    // Une requête Livewire refusée parce que la session est verrouillée : on affiche l'écran sans perdre la page.
    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (response.status === 423) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('session-locked'));
            }
        });
    });
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('sessionLock', (config) => ({
        locked: config.locked,
        pin: '',
        error: '',
        submitting: false,
        lastActivityAt: Date.now(),
        lastActivityWriteAt: 0,
        lastHeartbeatAt: Date.now(),

        init() {
            this.onActivity = () => this.recordActivity();
            this.onStorage = (event) => this.syncFromOtherTab(event);
            this.onPageShow = (event) => event.persisted && this.verifyWithServer();

            ACTIVITY_EVENTS.forEach((name) => window.addEventListener(name, this.onActivity, { passive: true }));
            window.addEventListener('storage', this.onStorage);
            window.addEventListener('pageshow', this.onPageShow);
            this.checkInterval = setInterval(() => this.check(), CHECK_INTERVAL_MS);

            // La marque est gardée dans le cache de wire:navigate : elle distingue une page restaurée d'une page rendue.
            const restored = this.$root.dataset.rendered !== undefined;
            this.$root.dataset.rendered = '';

            if (restored) {
                this.verifyWithServer();
            } else if (this.locked) {
                this.showLock();
            } else {
                this.shareState(false);
                this.recordActivity();
            }
        },

        destroy() {
            ACTIVITY_EVENTS.forEach((name) => window.removeEventListener(name, this.onActivity));
            window.removeEventListener('storage', this.onStorage);
            window.removeEventListener('pageshow', this.onPageShow);
            clearInterval(this.checkInterval);
        },

        sharedState() {
            try {
                return JSON.parse(readStorage(STATE_KEY)) ?? {};
            } catch {
                return {};
            }
        },

        /**
         * Une page rendue par le serveur fait foi : elle corrige l'état laissé par un déverrouillage pleine page.
         */
        shareState(locked) {
            if (this.sharedState().locked !== locked) {
                writeStorage(STATE_KEY, JSON.stringify({ locked, at: Date.now() }));
            }
        },

        async verifyWithServer() {
            if (this.sharedState().locked) {
                this.showLock();
            }

            this.lastHeartbeatAt = Date.now();
            const response = await this.post(config.heartbeatUrl);

            if (response.status === 401 || response.status === 419) {
                window.location.reload();

                return;
            }

            if (!response.ok) {
                return;
            }

            const { locked } = await response.json();

            if (locked) {
                this.showLock();
            } else if (this.locked) {
                this.markUnlocked();
            }

            this.shareState(locked);
        },

        recordActivity() {
            if (this.locked) {
                return;
            }

            const now = Date.now();
            this.lastActivityAt = now;

            if (now - this.lastActivityWriteAt >= ACTIVITY_WRITE_INTERVAL_MS) {
                this.lastActivityWriteAt = now;
                writeStorage(LAST_ACTIVITY_KEY, String(now));
            }
        },

        sharedLastActivityAt() {
            return Math.max(this.lastActivityAt, Number(readStorage(LAST_ACTIVITY_KEY)) || 0);
        },

        check() {
            if (this.locked) {
                return;
            }

            const lastActivityAt = this.sharedLastActivityAt();
            const now = Date.now();

            if (now - lastActivityAt >= config.idleSeconds * 1000) {
                this.lock();
            } else if (lastActivityAt > this.lastHeartbeatAt && now - this.lastHeartbeatAt >= HEARTBEAT_INTERVAL_MS) {
                this.heartbeat();
            }
        },

        async post(url, body = {}) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
        },

        async heartbeat() {
            this.lastHeartbeatAt = Date.now();

            const response = await this.post(config.heartbeatUrl);

            if (response.ok && (await response.json()).locked) {
                this.showLock();
                this.shareState(true);
            }
        },

        showLock() {
            this.locked = true;
            this.pin = '';
            this.error = '';

            // Rouvert même s'il l'est déjà, pour repasser par-dessus un modal ouvert entre-temps.
            if (this.$root.open) {
                this.$root.close();
            }

            this.$root.showModal();
            this.$nextTick(() => this.$root.querySelector('input[type="password"]')?.focus());
        },

        async lock() {
            this.showLock();
            this.shareState(true);

            await this.post(config.lockUrl);
        },

        async unlock() {
            if (this.submitting) {
                return;
            }

            this.submitting = true;
            this.error = '';

            try {
                const response = await this.post(config.unlockUrl, { pin: this.pin });
                const body = await response.json().catch(() => ({}));

                if (response.ok) {
                    this.markUnlocked();
                    this.shareState(false);
                } else if (body.redirect) {
                    window.location.href = body.redirect;
                } else if (response.status === 419) {
                    window.location.reload();
                } else {
                    this.error = body.errors?.pin?.[0] ?? body.message ?? '';
                    this.pin = '';
                }
            } finally {
                this.submitting = false;
            }
        },

        markUnlocked() {
            this.locked = false;
            this.$root.close();
            this.pin = '';
            this.error = '';
            this.lastHeartbeatAt = Date.now();
            this.lastActivityWriteAt = 0;
            this.recordActivity();
        },

        syncFromOtherTab(event) {
            if (event.key !== STATE_KEY || !event.newValue) {
                return;
            }

            const { locked } = JSON.parse(event.newValue);

            if (locked && !this.locked) {
                this.showLock();
            } else if (!locked && this.locked) {
                this.markUnlocked();
            }
        },
    }));
});

/**
 * Recherche « spotlight » des pages de la navigation. Les résultats sont rendus par le serveur selon les permissions ;
 * on ne fait que les filtrer, sans tenir compte des accents (le serveur fournit le texte de recherche déjà normalisé).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('spotlight', () => ({
        query: '',
        active: 0,
        visibleCount: 0,

        init() {
            this.filter();
        },

        onGlobalKeydown(event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.show();
            }
        },

        show() {
            this.query = '';
            this.filter();
            window.Flux.modal('spotlight').show();
            this.$nextTick(() => this.$refs.input.focus());
        },

        normalize(text) {
            return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        },

        items() {
            return [...this.$refs.results.querySelectorAll('[data-spotlight-item]')];
        },

        visibleItems() {
            return this.items().filter((item) => !item.hidden);
        },

        filter() {
            const terms = this.normalize(this.query).split(/\s+/).filter(Boolean);

            this.items().forEach((item) => {
                item.hidden = !terms.every((term) => item.dataset.search.includes(term));
            });

            this.$refs.results.querySelectorAll('[data-spotlight-section]').forEach((section) => {
                section.hidden = !section.querySelector('[data-spotlight-item]:not([hidden])');
            });

            this.visibleCount = this.visibleItems().length;
            this.active = 0;
            this.highlight();
        },

        move(step) {
            if (this.visibleCount === 0) {
                return;
            }

            this.active = (this.active + step + this.visibleCount) % this.visibleCount;
            this.highlight();
        },

        activate(item) {
            this.active = this.visibleItems().indexOf(item);
            this.highlight(false);
        },

        highlight(scroll = true) {
            this.visibleItems().forEach((item, index) => {
                item.toggleAttribute('data-active', index === this.active);

                if (scroll && index === this.active) {
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
        },

        openActive() {
            this.visibleItems()[this.active]?.click();
        },
    }));
});
