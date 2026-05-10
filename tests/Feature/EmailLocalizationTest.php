<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\WelcomeToGarageBookMail;
use Tests\TestCase;

class EmailLocalizationTest extends TestCase
{
    public function test_mail_subjects_use_default_locale_translations(): void
    {
        $this->assertSame('Welkom bij GarageBook', (new WelcomeToGarageBookMail())->envelope()->subject);
        $this->assertSame(
            'Nieuw contactbericht via GarageBook',
            (new ContactFormMail('Willem', 'willem@example.com', 'Testbericht'))->envelope()->subject
        );
    }

    public function test_contact_mail_view_uses_translated_labels(): void
    {
        $html = (new ContactFormMail('Willem', 'willem@example.com', 'Testbericht'))->render();

        $this->assertStringContainsString('Nieuw contactbericht via GarageBook', $html);
        $this->assertStringContainsString('Naam:', $html);
        $this->assertStringContainsString('E-mailadres:', $html);
        $this->assertStringContainsString('Vragen of opmerkingen:', $html);
    }
}
