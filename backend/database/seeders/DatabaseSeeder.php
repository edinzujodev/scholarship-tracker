<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Entities\Applicant;
use App\Entities\Application;
use App\Entities\Campaign;
use App\Enums\ApplicationStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Deterministic demo data with fixed ids, so the README's manual API checks
 * (and the frontend mock) can reference known records.
 * Run after `php artisan doctrine:schema:create`.
 *
 * Applicants (people) are a shared pool: 00000000-0000-4000-a000-0000000000NN.
 * Applications link one person to one campaign:
 *
 * partner-demo  (token: demo-partner-token)
 *   9b2f0f7e-…  Global Scholars Programme 2026   28 applications  00000000-0000-4000-8000-0000000000NN   people 1–28
 *   5d8e1c2a-…  Engineering Fellowship 2026      12 applications  00000000-0000-4000-8001-0000000000NN   people 1–5 and 29–35
 *   3c6e2b8a-…  Spring Pilot (no applicants yet)  0 applications
 * partner-other (token: other-partner-token)
 *   7a1b2c3d-…  Northbridge Bursary 2026           3 applications  00000000-0000-4000-8002-0000000000NN   people 36–38
 *
 * People 1–5 applied to both Global Scholars and Engineering Fellowship, each
 * with its own score and status.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_CAMPAIGN_ID = '9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01';
    public const FELLOWSHIP_CAMPAIGN_ID = '5d8e1c2a-3b4f-4c6d-9e7f-8a9b0c1d2e3f';
    public const EMPTY_CAMPAIGN_ID = '3c6e2b8a-1f4d-4e5a-8b7c-2d9e0f1a2b3c';
    public const OTHER_PARTNER_CAMPAIGN_ID = '7a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';

    private const FIRST = ['Amara', 'Luka', 'Sofia', 'Kenji', 'Leila', 'Mateo', 'Ines', 'Noah', 'Zara', 'Emil', 'Yara', 'Omar', 'Hana'];
    private const LAST = ['Okafor', 'Horvat', 'Rossi', 'Tanaka', 'Haddad', 'Silva', 'Kovač', 'Berg', 'Nguyen', 'Fischer'];
    private const STRONG = [
        'Top 5% in quantitative reasoning assessment',
        'Led a 12-person robotics team to national finals',
        'Personal statement strongly aligned with programme goals',
        'Fluent in three languages, including the programme language',
        'Two relevant internships in data engineering',
        'Recommendation letters describe exceptional initiative',
    ];
    private const WEAK = [
        'Recommendation letters describe exceptional initiative',
        'Essay lacks concrete examples of impact',
        'Missing one required transcript (flagged for follow-up)',
        'Volunteer tutoring record of 200+ hours',
        'Portfolio shows strong applied ML projects',
    ];

    /** @var array<int, Applicant> */
    private array $people = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function run(): void
    {
        if ($this->em->find(Campaign::class, self::DEMO_CAMPAIGN_ID)) {
            $this->command?->info('Demo data already present, skipping.');

            return;
        }

        $this->seedCampaign(self::DEMO_CAMPAIGN_ID, 'partner-demo', 'Global Scholars Programme 2026', range(1, 28), 2026, '8000');
        $this->seedCampaign(self::FELLOWSHIP_CAMPAIGN_ID, 'partner-demo', 'Engineering Fellowship 2026', [...range(1, 5), ...range(29, 35)], 7, '8001');
        $this->seedCampaign(self::EMPTY_CAMPAIGN_ID, 'partner-demo', 'Spring Pilot (no applicants yet)', [], 1, '0000');
        $this->seedCampaign(self::OTHER_PARTNER_CAMPAIGN_ID, 'partner-other', 'Northbridge Bursary 2026', range(36, 38), 99, '8002');

        $this->em->flush();
        $this->command?->info(sprintf('Seeded 4 campaigns, %d applicants, 43 applications.', count($this->people)));
    }

    /** One shared Applicant per person number, created on first use. */
    private function person(int $n): Applicant
    {
        if (! isset($this->people[$n])) {
            // Middle name deliberately left empty in the demo data.
            $this->people[$n] = new Applicant(
                firstName: self::FIRST[($n - 1) % count(self::FIRST)],
                lastName: self::LAST[(($n - 1) * 7) % count(self::LAST)],
                email: "applicant{$n}@example.test",
                id: sprintf('00000000-0000-4000-a000-%012d', $n),
            );
            $this->em->persist($this->people[$n]);
        }

        return $this->people[$n];
    }

    /** @param list<int> $personNumbers */
    private function seedCampaign(string $id, string $partnerId, string $name, array $personNumbers, int $seed, string $idGroup): void
    {
        $campaign = new Campaign($partnerId, $name, $id);
        $this->em->persist($campaign);

        mt_srand($seed);
        foreach ($personNumbers as $i => $n) {
            $score = mt_rand(35, 99);
            $pool = $score >= 70 ? self::STRONG : self::WEAK;
            $application = new Application(
                applicant: $this->person($n),
                campaign: $campaign,
                aiScore: $score,
                eligible: $score >= 60 && $i % 5 !== 0,
                aiHighlights: array_values(array_unique([
                    $pool[$i % count($pool)],
                    $pool[($i + 2) % count($pool)],
                    $pool[($i + 3) % count($pool)],
                ])),
                appliedAt: new DateTimeImmutable(sprintf('2026-09-%02d 09:%02d:00', 1 + ($i % 28), $i)),
                id: sprintf('00000000-0000-4000-%s-%012d', $idGroup, $i + 1),
            );
            if ($i % 9 === 0) {
                $application->changeStatus(ApplicationStatus::Shortlisted);
            }
            $this->em->persist($application);
        }
    }
}
