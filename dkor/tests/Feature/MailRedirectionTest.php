<?php

use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie un courriel à un fournisseur par un service d'envoi « réel » (simulé par le transport array).
 *
 * @return Collection<int, Email>
 */
function sendToSupplier(array $config): Collection
{
    config(['mail.mailers.postmark' => ['transport' => 'array'], 'mail.default' => 'postmark', ...$config]);
    Mail::purge('postmark');

    Mail::raw('Commande 1001', fn (Message $message) => $message
        ->to('commandes@fournisseur.example')
        ->cc('copie@fournisseur.example')
        ->bcc('cachee@fournisseur.example')
        ->subject('Commande 1001'));

    return Mail::mailer('postmark')->getSymfonyTransport()->messages()->map->getOriginalMessage();
}

function addresses(array $addresses): array
{
    return array_map(fn (Address $address): string => $address->getAddress(), $addresses);
}

it('envoie chaque courriel à l\'adresse de redirection, sans copie ni copie cachée', function () {
    $sent = sendToSupplier(['mail.to.address' => 'boite@dkor.test']);

    expect($sent)->toHaveCount(1)
        ->and(addresses($sent->first()->getTo()))->toBe(['boite@dkor.test'])
        ->and($sent->first()->getCc())->toBe([])
        ->and($sent->first()->getBcc())->toBe([]);
});

it('n\'envoie rien par un service réel sans adresse de redirection', function () {
    expect(sendToSupplier(['mail.to.address' => null]))->toBeEmpty();
});

it('envoie aux vrais destinataires seulement si c\'est permis explicitement', function () {
    $sent = sendToSupplier(['mail.to.address' => null, 'mail.allow_real_recipients' => true]);

    expect(addresses($sent->first()->getTo()))->toBe(['commandes@fournisseur.example']);
});
