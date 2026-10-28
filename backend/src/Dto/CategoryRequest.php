<?php

namespace App\Dto;

use App\Entity\Category;
use App\Enum\CategoryKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CategoryRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un nom à la catégorie.', normalizer: 'trim')]
        #[Assert\Length(max: Category::NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public CategoryKind $kind = CategoryKind::Expense,
        #[Assert\Regex('/^#[0-9a-fA-F]{6}$/', message: 'Choisissez une couleur.')]
        public string $color = '#8d8d8d',
        /** In cents; null for no budget. Ignored for income. */
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Le budget mensuel doit être positif.')]
        public ?int $monthlyBudget = null,
    ) {
    }
}
