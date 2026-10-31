<?php

namespace App\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Locked;

/**
 * Fiche à onglets dont chaque onglet a son formulaire : changer d'onglet sauvegarde l'onglet quitté s'il a été
 * modifié (une erreur de validation garde l'usager sur l'onglet), et « Sauvegarder et fermer » sauvegarde l'onglet
 * courant puis retourne à la liste. Un usager qui ne peut pas sauvegarder change d'onglet sans sauvegarde.
 *
 * @property string $activeTab
 */
trait SavesTabsOnSwitch
{
    /** @var array<string, string> Empreinte des champs de chaque onglet à sa dernière sauvegarde. */
    #[Locked]
    public array $savedTabFingerprints = [];

    /**
     * Méthode de sauvegarde et champs (notation pointée) de chaque onglet qui a un formulaire.
     *
     * @return array<string, array{save: string, fields: array<int, string>}>
     */
    abstract protected function tabForms(): array;

    /** Route de la liste où retourne « Sauvegarder et fermer ». */
    abstract protected function closeRoute(): string;

    public function dehydrateSavesTabsOnSwitch(): void
    {
        if ($this->savedTabFingerprints === []) {
            $this->rememberTabs(array_keys($this->tabForms()));
        }
    }

    public function switchTab(string $tab): void
    {
        if ($tab !== $this->activeTab && $this->saveTabIfModified($this->activeTab)) {
            $this->activeTab = $tab;
        }
    }

    public function saveAndClose(): void
    {
        if ($this->saveTabIfModified($this->activeTab)) {
            $this->redirectRoute($this->closeRoute(), navigate: true);
        }
    }

    /**
     * Sauvegarde l'onglet s'il a été modifié depuis sa dernière sauvegarde. Faux si la sauvegarde a échoué (les erreurs
     * de validation remontent à l'usager).
     */
    private function saveTabIfModified(string $tab): bool
    {
        $form = $this->tabForms()[$tab] ?? null;

        if ($form === null || ! $this->isTabModified($tab)) {
            return true;
        }

        // Une sauvegarde peut recharger les champs d'un autre onglet (ex.: numéros de taxes selon la province) : les
        // onglets intacts gardent alors leur nouvel état comme référence.
        $untouchedTabs = array_filter(array_keys($this->tabForms()), fn (string $other): bool => $other !== $tab && ! $this->isTabModified($other));

        $this->resetErrorBag();

        try {
            $this->{$form['save']}();
        } catch (AuthorizationException) {
            return true;
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return false;
        }

        $this->rememberTabs([$tab, ...$untouchedTabs]);

        return true;
    }

    private function isTabModified(string $tab): bool
    {
        return $this->tabFingerprint($tab) !== ($this->savedTabFingerprints[$tab] ?? null);
    }

    /**
     * @param  array<int, string>  $tabs
     */
    private function rememberTabs(array $tabs): void
    {
        foreach ($tabs as $tab) {
            $this->savedTabFingerprints[$tab] = $this->tabFingerprint($tab);
        }
    }

    private function tabFingerprint(string $tab): string
    {
        return md5(json_encode(array_map(fn (string $field): mixed => data_get($this, $field), $this->tabForms()[$tab]['fields'])));
    }
}
