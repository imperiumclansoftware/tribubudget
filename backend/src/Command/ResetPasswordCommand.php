<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Security\SecurityAudit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * For the server's administrator, when no parent can sign in any more (the only parent forgot
 * their password): TribuBudget sends no e-mail, see docs/adr/0003-password-reset.md. Within the
 * family, a parent resets the password of any other member from the app.
 *
 * The password is typed hidden, never given as an argument (it would stay in the shell history).
 */
#[AsCommand(name: 'app:user:reset-password', description: 'Change le mot de passe (ou le code) d’un membre de la famille')]
final readonly class ResetPasswordCommand
{
    public function __construct(
        private UserRepository $users,
        private PasswordPolicy $passwordPolicy,
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
        private SecurityAudit $audit,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Identifiant de connexion du membre')] string $username,
    ): int {
        $member = $this->users->findOneBy(['username' => mb_strtolower($username)]);
        if (null === $member) {
            $io->error(\sprintf('Aucun membre n’a l’identifiant « %s ».', $username));

            return Command::FAILURE;
        }

        $what = $member->isChild() ? 'code (4 à 12 chiffres)' : 'mot de passe (12 caractères ou plus)';
        $password = $io->askHidden(\sprintf('Nouveau %s de %s', $what, $member->getDisplayName()));
        if (!\is_string($password) || $password !== $io->askHidden('Retapez-le')) {
            $io->error('Les deux saisies ne sont pas identiques : rien n’a changé.');

            return Command::FAILURE;
        }
        try {
            $this->passwordPolicy->check($member->getRole(), $password);
        } catch (UnprocessableEntityHttpException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $member->setPassword($this->hasher->hashPassword($member, $password));
        $this->em->flush();
        $this->audit->passwordResetOnServer($member);
        $io->success(\sprintf('Le %s de %s est changé. Ses autres sessions sont fermées.', $member->isChild() ? 'code' : 'mot de passe', $member->getDisplayName()));

        return Command::SUCCESS;
    }
}
