<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class VehicleAuthorityService
{
    private const CACHE_TTL = 1800;

    private const RELATED_PAGES = [
        ['url' => '/digitaal-onderhoudsboekje', 'title' => 'Digitaal onderhoudsboekje', 'description' => 'Centrale voertuiggeschiedenis voor auto en motor.'],
        ['url' => '/onderhoudsboekje-kwijt', 'title' => 'Onderhoudsboekje kwijt?', 'description' => 'Stappen om je onderhoudshistorie te reconstrueren.'],
        ['url' => '/onderhoudshistorie-auto', 'title' => 'Onderhoudshistorie', 'description' => 'Alles over onderhoudshistorie opbouwen en overdragen.'],
        ['url' => '/universeel-onderhoudsboekje', 'title' => 'Universeel onderhoudsboekje', 'description' => 'Voordelen, nadelen en digitale alternatieven.'],
    ];

    public function __construct(
        private readonly VehicleAuthorityIndexService $indexService,
    ) {}

    public function resolveBySlug(string $slug): ?array
    {
        return Cache::remember("vehicle-authority:page:{$slug}", self::CACHE_TTL, function () use ($slug) {
            return $this->build($slug);
        });
    }

    /**
     * @return Collection<int, string>
     */
    public function allModelSlugs(): Collection
    {
        return $this->indexService->allIndexableSlugs();
    }

    private function build(string $slug): ?array
    {
        $entry = $this->indexService->resolveBySlug($slug);

        if ($entry === null) {
            return null;
        }

        $brand = $entry->brand;
        $model = $entry->model;

        $vehicles = Vehicle::query()
            ->join('users', 'vehicles.user_id', '=', 'users.id')
            ->where('vehicles.is_public', true)
            ->where('users.is_outreach_demo', false)
            ->where('vehicles.brand', $brand)
            ->where('vehicles.model', $model)
            ->whereNotNull('vehicles.public_slug')
            ->where('vehicles.public_slug', '!=', '')
            ->with([
                'maintenanceLogs' => fn ($q) => $q->latest('maintenance_date')->latest('id'),
            ])
            ->select('vehicles.*')
            ->orderBy('vehicles.public_slug')
            ->take(5)
            ->get();

        if ($vehicles->isEmpty()) {
            return null;
        }

        return [
            'brand' => $brand,
            'model' => $model,
            'slug' => $slug,
            'category' => $entry->category,
            'year_range' => $this->yearRange($vehicles),
            'public_vehicles' => $vehicles,
            'related_models' => $this->indexService->relatedModels($brand, $model, 8),
            'related_pages' => self::RELATED_PAGES,
            'faq_items' => $this->faqItems($brand, $model),
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function yearRange(Collection $vehicles): ?string
    {
        $years = $vehicles->pluck('year')->filter()->sort()->values();

        if ($years->isEmpty()) {
            return null;
        }

        $min = $years->first();
        $max = $years->last();

        return $min === $max ? (string) $min : "{$min}–{$max}";
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    private function faqItems(string $brand, string $model): array
    {
        return [
            [
                'question' => "Hoe houd ik het onderhoud van een {$brand} {$model} bij?",
                'answer' => "Met GarageBook leg je iedere onderhoudsactie van je {$brand} {$model} vast: datum, kilometerstand, uitgevoerde werkzaamheden, gebruikte onderdelen, foto's en facturen. Zo bouw je stap voor stap een volledige onderhoudshistorie op die overdraagbaar is bij verkoop.",
            ],
            [
                'question' => "Bestaat er een digitaal onderhoudsboekje voor de {$brand} {$model}?",
                'answer' => "Ja. GarageBook werkt voor ieder voertuig, ook de {$brand} {$model}. Je maakt een voertuigprofiel aan met merk en model en begint direct met het bijhouden van beurten, reparaties en documenten. Starten is gratis.",
            ],
            [
                'question' => "Wat zijn de meest kritische onderhoudspunten voor een {$brand} {$model}?",
                'answer' => "De meest kritische onderhoudspunten verschillen per specifiek model en bouwjaar. Raadpleeg altijd de instructies van {$brand} voor de aanbevolen onderhoudsmomenten. Met GarageBook leg je de daadwerkelijk uitgevoerde werkzaamheden vast, inclusief kilometerstand en bewijs.",
            ],
            [
                'question' => "Hoe bouw ik een aantoonbare onderhoudshistorie op van mijn {$brand} {$model}?",
                'answer' => "Log iedere beurt direct na uitvoering in GarageBook: datum, kilometerstand, beschrijving van de werkzaamheden, gebruikte onderdelen en eventuele foto's en facturen. Doe dit ook voor eigen onderhoud. Zo is de complete onderhoudshistorie van je {$brand} {$model} altijd beschikbaar en overdraagbaar.",
            ],
            [
                'question' => "Helpt een onderhoudshistorie bij de verkoop van een {$brand} {$model}?",
                'answer' => "Ja. Kopers zijn bereid meer te betalen voor een {$brand} {$model} met aantoonbare onderhoudshistorie. Een complete tijdlijn in GarageBook maakt je aanbieding geloofwaardiger en versnelt het verkoopproces.",
            ],
        ];
    }
}
