/**
 * Verrouillage de la session après inactivité (ADR 0015).
 *
 * L'inactivité est partagée entre les onglets par le localStorage : travailler dans un onglet garde les autres
 * ouverts, et verrouiller ou déverrouiller un onglet fait de même pour les autres. Le serveur reste la source de vérité :
 * il refuse les requêtes d'une session verrouillée, et l'activité sur une page déjà chargée lui est signalée par un
 * battement de cœur.
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

            ACTIVITY_EVENTS.forEach((name) => window.addEventListener(name, this.onActivity, { passive: true }));
            window.addEventListener('storage', this.onStorage);
            this.checkInterval = setInterval(() => this.check(), CHECK_INTERVAL_MS);

            if (this.locked) {
                this.showLock();
            } else {
                this.recordActivity();
            }
        },

        destroy() {
            ACTIVITY_EVENTS.forEach((name) => window.removeEventListener(name, this.onActivity));
            window.removeEventListener('storage', this.onStorage);
            clearInterval(this.checkInterval);
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
            writeStorage(STATE_KEY, JSON.stringify({ locked: true, at: Date.now() }));

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
                    writeStorage(STATE_KEY, JSON.stringify({ locked: false, at: Date.now() }));
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
