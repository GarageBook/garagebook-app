<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PhpSecurityServiceMail extends Mailable
{
    public const SUBJECT = 'GarageBook vanmiddag tijdelijk niet bereikbaar';

    public const BODY = "Beste GarageBook-gebruiker,\n\nVanwege een recent ontdekt beveiligingslek in PHP, de software waarop GarageBook draait, halen we de website vanmiddag uit voorzorg tijdelijk offline.\n\nWe wachten op een beveiligingsupdate en voeren deze zo snel mogelijk door. Daarna maken we GarageBook weer beschikbaar.\n\nExcuses voor het ongemak en bedankt voor je begrip!\n\nGroet,\n\nHet GarageBook-team";

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECT);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.php-security-service', text: 'emails.php-security-service-text', with: ['body' => self::BODY]);
    }
}
