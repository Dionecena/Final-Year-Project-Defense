<?php

namespace App\Notifications;

use App\Models\RendezVous;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Courriel envoyé au patient au sujet d'un rendez-vous (section 9) : canal mail uniquement, via la
 * file d'attente, après validation de la transaction (afterCommit).
 *
 * L'objet et les lignes du courriel sont figés à la création : le journal (table notifications)
 * et le courriel ont le même contenu, même si le rendez-vous change avant le passage de la file.
 * Le courriel indique la date, l'heure, le médecin, la spécialité et les coordonnées de la clinique
 * (config/clinique.php). Il ne contient AUCUNE donnée de préconsultation.
 *
 * Utilisation : NotificationService::envoyer(), qui crée la ligne du journal et renseigne journalId ;
 * date_envoi est renseignée par l'écouteur de NotificationSent quand l'envoi a réussi.
 */
abstract class NotificationRendezVous extends Notification implements ShouldQueue
{
    use Queueable;

    /** Identifiant de la ligne du journal (table notifications). */
    public ?int $journalId = null;

    public int $rendezVousId;

    public string $objet;

    /** @var list<string> paragraphes du courriel */
    public array $lignes;

    public function __construct(RendezVous $rendezVous)
    {
        $rendezVous->loadMissing(['medecin.user', 'medecin.specialite']);

        $this->rendezVousId = $rendezVous->id;
        $this->objet = $this->construireObjet();
        $this->lignes = [
            ...$this->introduction($rendezVous),
            ...self::detail($rendezVous),
            ...$this->conclusion(),
            ...self::coordonneesClinique(),
        ];

        $this->afterCommit();
    }

    /** Objet du courriel. */
    abstract protected function construireObjet(): string;

    /** @return list<string> phrases qui précèdent le détail du rendez-vous */
    abstract protected function introduction(RendezVous $rendezVous): array;

    /** @return list<string> phrases qui suivent le détail du rendez-vous */
    protected function conclusion(): array
    {
        return ['Vous pouvez toujours vous présenter directement à l\'accueil de la clinique.'];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->objet)
            ->greeting('Bonjour '.trim($notifiable->prenom.' '.$notifiable->nom).',')
            ->salutation('Cordialement,  '."\n".config('clinique.nom'));

        foreach ($this->lignes as $ligne) {
            $message->line($ligne);
        }

        return $message;
    }

    /** Contenu enregistré dans le journal : les paragraphes du courriel, en texte brut. */
    public function contenu(): string
    {
        return implode("\n\n", $this->lignes);
    }

    /** @return list<string> date, heure, médecin et spécialité */
    protected static function detail(RendezVous $rendezVous): array
    {
        return [
            'Date : '.self::date($rendezVous->date_heure),
            'Heure : '.$rendezVous->date_heure->format('H:i'),
            'Médecin : '.$rendezVous->medecin->user->prenom.' '.$rendezVous->medecin->user->nom,
            'Spécialité : '.$rendezVous->medecin->specialite->libelle,
        ];
    }

    /** « lundi 2 novembre 2026 ». */
    protected static function date(CarbonInterface $date): string
    {
        return $date->locale('fr')->translatedFormat('l j F Y');
    }

    /** « lundi 2 novembre 2026 à 09:00 ». */
    protected static function dateHeure(CarbonInterface $date): string
    {
        return self::date($date).' à '.$date->format('H:i');
    }

    /** @return list<string> coordonnées de la clinique renseignées dans .env (les vides sont omises) */
    private static function coordonneesClinique(): array
    {
        $coordonnees = array_filter([
            config('clinique.adresse') ? 'Adresse : '.config('clinique.adresse') : null,
            config('clinique.telephone') ? 'Téléphone : '.config('clinique.telephone') : null,
            config('clinique.email') ? 'Courriel : '.config('clinique.email') : null,
        ]);

        return $coordonnees === [] ? [] : [config('clinique.nom').' – '.implode(' · ', $coordonnees)];
    }
}
