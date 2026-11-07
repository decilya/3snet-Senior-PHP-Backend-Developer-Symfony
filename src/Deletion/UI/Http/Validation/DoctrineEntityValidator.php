<?php

declare(strict_types=1);

namespace App\Deletion\UI\Http\Validation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Валидатор для ограничения DoctrineEntity.
 *
 * Гарантирует, что запрошенный класс зарегистрирован в метаданных Doctrine.
 */
final class DoctrineEntityValidator extends ConstraintValidator
{
    /**
     * @param EntityManagerInterface $em
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param mixed      $value
     * @param Constraint $constraint
     *
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DoctrineEntity) {
            throw new UnexpectedTypeException($constraint, DoctrineEntity::class);
        }

        if (!is_string($value) || $value === '') {
            return;
        }

        try {
            $this->em->getMetadataFactory()->getMetadataFor($value);
        } catch (MappingException) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
