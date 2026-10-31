<?php

namespace App\Controller;

use App\Api\ApiPresenter;
use App\Api\FamilyLookup;
use App\Api\VehicleCatalog;
use App\Dto\MaintenanceDoneRequest;
use App\Dto\MaintenanceRequest;
use App\Dto\RefuelRequest;
use App\Dto\Text;
use App\Dto\VehicleRequest;
use App\Entity\Account;
use App\Entity\Maintenance;
use App\Entity\Refuel;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Money\Garage;
use App\Money\Month;
use App\Money\Today;
use App\Repository\MaintenanceRepository;
use App\Repository\OperationRepository;
use App\Repository\RefuelRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The family's vehicles: fill-ups against a monthly fuel budget, maintenance planned with a budget
 * of its own, odometer (see Garage). Paid from the household's accounts: parents only.
 */
#[Route('/api', format: 'json')]
#[IsGranted('ROLE_PARENT', message: 'Seuls les parents gèrent les véhicules.')]
final class VehicleController extends AbstractController
{
    use FamilyScope;

    public function __construct(
        private readonly ApiPresenter $presenter,
        private readonly EntityManagerInterface $em,
        private readonly Garage $garage,
        private readonly FamilyLookup $lookup,
        private readonly RefuelRepository $refuels,
        private readonly MaintenanceRepository $maintenances,
        private readonly OperationRepository $operations,
        private readonly Today $today,
    ) {
    }

    #[Route('/vehicles', name: 'api_vehicles', methods: ['GET'])]
    public function list(#[CurrentUser] User $me, VehicleRepository $vehicles): JsonResponse
    {
        return $this->json(array_map($this->presented(...), $vehicles->forFamily($me->getFamily())));
    }

    /** A vehicle with its fill-ups and its maintenance. */
    #[Route('/vehicles/{id}', name: 'api_vehicle', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(Vehicle $vehicle, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameFamily($vehicle, $me);

        return $this->json($this->detailed($vehicle));
    }

    #[Route('/vehicles', name: 'api_vehicles_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $me, #[MapRequestPayload] VehicleRequest $payload): JsonResponse
    {
        $vehicle = new Vehicle($me->getFamily(), Text::clean($payload->name));
        $this->describe($vehicle, $payload);
        $this->em->persist($vehicle);
        $this->em->flush();

        return $this->json($this->detailed($vehicle), Response::HTTP_CREATED);
    }

    #[Route('/vehicles/{id}', name: 'api_vehicles_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function update(Vehicle $vehicle, #[CurrentUser] User $me, #[MapRequestPayload] VehicleRequest $payload): JsonResponse
    {
        $this->sameFamily($vehicle, $me);
        $previousName = $vehicle->getName();
        $this->describe($vehicle, $payload, $previousName);
        $this->em->flush();

        return $this->json($this->detailed($vehicle));
    }

    /**
     * Its fill-ups and maintenance go with it; their expenses stay in the operations. Its fuel
     * category goes too, or, when it has expenses, stays for them without its budget.
     */
    #[Route('/vehicles/{id}', name: 'api_vehicles_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function delete(Vehicle $vehicle, #[CurrentUser] User $me): Response
    {
        $this->sameFamily($vehicle, $me);
        $this->garage->retire($vehicle);
        $this->em->remove($vehicle);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/vehicles/{id}/refuels', name: 'api_refuels_create', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function refuel(Vehicle $vehicle, #[CurrentUser] User $me, #[MapRequestPayload] RefuelRequest $payload): JsonResponse
    {
        $this->sameFamily($vehicle, $me);
        $this->garage->refuel($vehicle, $this->account($payload->accountId, $me), $payload->amount, $payload->volume, $payload->odometer, new \DateTimeImmutable($payload->date), $me);
        $this->em->flush();

        return $this->json($this->detailed($vehicle), Response::HTTP_CREATED);
    }

    #[Route('/refuels/{id}', name: 'api_refuels_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function updateRefuel(Refuel $refuel, #[CurrentUser] User $me, #[MapRequestPayload] RefuelRequest $payload): JsonResponse
    {
        $this->sameFamily($refuel, $me);
        $this->garage->editRefuel($refuel, $this->account($payload->accountId, $me), $payload->amount, $payload->volume, $payload->odometer, new \DateTimeImmutable($payload->date));
        $this->em->flush();

        return $this->json($this->detailed($refuel->getVehicle()));
    }

    /** With its expense. */
    #[Route('/refuels/{id}', name: 'api_refuels_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function deleteRefuel(Refuel $refuel, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameFamily($refuel, $me);
        $vehicle = $refuel->getVehicle();
        $this->em->remove($refuel->getOperation());
        $this->em->remove($refuel);
        $this->em->flush();

        return $this->json($this->detailed($vehicle));
    }

    #[Route('/vehicles/{id}/maintenances', name: 'api_maintenances_create', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function plan(Vehicle $vehicle, #[CurrentUser] User $me, #[MapRequestPayload] MaintenanceRequest $payload): JsonResponse
    {
        $this->sameFamily($vehicle, $me);
        $this->em->persist(new Maintenance($vehicle, Text::clean($payload->label), new \DateTimeImmutable($payload->plannedOn), $payload->budget, $payload->plannedOdometer));
        $this->em->flush();

        return $this->json($this->detailed($vehicle), Response::HTTP_CREATED);
    }

    #[Route('/maintenances/{id}', name: 'api_maintenances_update', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function replan(Maintenance $maintenance, #[CurrentUser] User $me, #[MapRequestPayload] MaintenanceRequest $payload): JsonResponse
    {
        $this->sameFamily($maintenance, $me);
        $maintenance->plan(Text::clean($payload->label), new \DateTimeImmutable($payload->plannedOn), $payload->budget, $payload->plannedOdometer);
        $this->em->flush();

        return $this->json($this->detailed($maintenance->getVehicle()));
    }

    /** A maintenance done keeps its expense in the operations. */
    #[Route('/maintenances/{id}', name: 'api_maintenances_delete', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function deleteMaintenance(Maintenance $maintenance, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameFamily($maintenance, $me);
        $vehicle = $maintenance->getVehicle();
        $this->em->remove($maintenance);
        $this->em->flush();

        return $this->json($this->detailed($vehicle));
    }

    #[Route('/maintenances/{id}/done', name: 'api_maintenances_done', requirements: ['id' => Requirement::DIGITS], methods: ['PUT'])]
    public function done(Maintenance $maintenance, #[CurrentUser] User $me, #[MapRequestPayload] MaintenanceDoneRequest $payload): JsonResponse
    {
        $this->sameFamily($maintenance, $me);
        if ($maintenance->isDone()) {
            throw new UnprocessableEntityHttpException('Cet entretien est déjà fait.');
        }
        $this->garage->complete($maintenance, $this->account($payload->accountId, $me), $payload->amount, new \DateTimeImmutable($payload->date), $payload->odometer, $me);
        $this->em->flush();

        return $this->json($this->detailed($maintenance->getVehicle()));
    }

    /** Not done after all: its expense is deleted. */
    #[Route('/maintenances/{id}/done', name: 'api_maintenances_reopen', requirements: ['id' => Requirement::DIGITS], methods: ['DELETE'])]
    public function reopen(Maintenance $maintenance, #[CurrentUser] User $me): JsonResponse
    {
        $this->sameFamily($maintenance, $me);
        $this->garage->reopen($maintenance);
        $this->em->flush();

        return $this->json($this->detailed($maintenance->getVehicle()));
    }

    /**
     * Makes, then the models of a make, then the versions of a model, of the cars sold new in
     * France (VehicleCatalog); empty when the ADEME cannot be reached.
     */
    #[Route('/vehicle-catalog', name: 'api_vehicle_catalog', methods: ['GET'])]
    public function catalog(VehicleCatalog $catalog, #[MapQueryParameter] ?string $make = null, #[MapQueryParameter] ?string $model = null): JsonResponse
    {
        $make = VehicleRequest::optional($make);
        $model = VehicleRequest::optional($model);

        return $this->json(match (true) {
            null === $make => ['makes' => $catalog->makes()],
            null === $model => ['models' => $catalog->models($make)],
            default => ['versions' => $catalog->versions($make, $model)],
        });
    }

    private function describe(Vehicle $vehicle, VehicleRequest $payload, ?string $previousName = null): void
    {
        $vehicle->describe(
            Text::clean($payload->name),
            $payload->energy,
            VehicleRequest::optional($payload->plate),
            VehicleRequest::optional($payload->make),
            VehicleRequest::optional($payload->model),
            VehicleRequest::optional($payload->version),
            null === $payload->firstRegistration || '' === $payload->firstRegistration ? null : new \DateTimeImmutable($payload->firstRegistration),
            $payload->officialConsumption,
        );
        if ($payload->odometer !== $vehicle->getOdometer()) {
            $vehicle->setOdometer($payload->odometer, $this->today->date());
        }
        $this->garage->fuel($vehicle, $payload->fuelBudget, $previousName);
    }

    /** A household account: the vehicles are paid by the household */
    private function account(?int $id, User $me): Account
    {
        /** @var Account $account validated not null */
        $account = $this->lookup->householdAccount($id, $me);

        return $account;
    }

    /** @return array<string, mixed> */
    private function presented(Vehicle $vehicle): array
    {
        $month = Month::of($this->today->date());
        $spent = null === $vehicle->getFuelCategory() ? [] : $this->operations->spentByCategory($vehicle->getFamily(), $month->start, $month->end());
        $due = array_values(array_filter($this->maintenances->forVehicle($vehicle), static fn (Maintenance $m) => !$m->isDone()));

        return $this->presenter->vehicle(
            $vehicle,
            $spent[$vehicle->getFuelCategory()?->getId()] ?? 0,
            $this->refuels->consumption($vehicle),
            $due[0] ?? null,
        );
    }

    /** @return array<string, mixed> */
    private function detailed(Vehicle $vehicle): array
    {
        return $this->presented($vehicle) + [
            'refuels' => array_map($this->presenter->refuel(...), $this->refuels->history($vehicle)),
            'maintenances' => array_map($this->presenter->maintenance(...), $this->maintenances->forVehicle($vehicle)),
        ];
    }
}
