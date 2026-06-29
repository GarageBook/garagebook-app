<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GrowthProspectOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $trackingUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gratis digitaal onderhoudsboekje voor jullie leden',
            replyTo: [new Address('social@garagebook.nl', 'GarageBook Social')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.growth.prospect-outreach',
            text: 'emails.growth.prospect-outreach-text',
            with: [
                'bodyText' => $this->bodyText(),
            ],
        );
    }

    public function bodyText(): string
    {
        return implode(PHP_EOL, [
            'Hoi '.$this->recipientName.',',
            '',
            'Ik ben Willem, maker van GarageBook: een gratis digitaal onderhoudsboekje voor motoren.',
            '',
            'Ik denk dat dit interessant kan zijn voor jullie leden: ze kunnen onderhoud, documenten, foto’s en historie van hun motor netjes bijhouden — handig bij onderhoud, verkoop, taxatie of gewoon om de geschiedenis compleet te houden.',
            '',
            'Ik heb een speciale link voor jullie klaargezet:',
            $this->trackingUrl,
            '',
            'GarageBook is gratis te gebruiken voor één voertuig. Het zou mooi zijn als jullie dit eens willen bekijken en eventueel delen met leden, bijvoorbeeld in een nieuwsbrief of clubbericht.',
            '',
            'Geen commerciële verplichting of gedoe; vooral een handig hulpmiddel voor motorrijders die hun motor serieus nemen.',
            '',
            'Groet,',
            'Willem',
            'GarageBook',
            'https://garagebook.nl',
        ]);
    }
}
