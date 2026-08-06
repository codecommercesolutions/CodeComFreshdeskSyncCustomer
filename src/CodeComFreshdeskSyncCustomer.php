<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskSyncCustomer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CodeComFreshdeskSyncCustomer extends Plugin
{
    public const CUSTOM_FIELD_SET_NAME = 'freshdesk_customer_set';
    public const CUSTOM_FIELD_API_RESPONSE = 'freshdesk_api_response';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->createCustomFields($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->createCustomFields($updateContext->getContext());
    }

    public function createCustomFields(Context $context): void
    {
        /** @var EntityRepository|null $customFieldSetRepository */
        $customFieldSetRepository = $this->container?->get('custom_field_set.repository');
        if (!$customFieldSetRepository) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET_NAME));

        $existingSet = $customFieldSetRepository->search($criteria, $context)->first();
        if ($existingSet === null) {
            $customFieldSetRepository->create([
                [
                    'name' => self::CUSTOM_FIELD_SET_NAME,
                    'config' => [
                        'label' => [
                            'en-GB' => 'Freshdesk',
                            'de-DE' => 'Freshdesk',
                        ],
                    ],
                    'relations' => [
                        [
                            'entityName' => 'customer',
                        ],
                    ],
                    'customFields' => [
                        [
                            'name' => self::CUSTOM_FIELD_API_RESPONSE,
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Freshdesk API Response',
                                    'de-DE' => 'Freshdesk API-Antwort',
                                ],
                                'componentName' => 'sw-field',
                                'customFieldType' => 'text',
                                'customFieldPosition' => 1,
                            ],
                        ],
                    ],
                ],
            ], $context);

            return;
        }

        /** @var EntityRepository|null $customFieldRepository */
        $customFieldRepository = $this->container?->get('custom_field.repository');
        if ($customFieldRepository) {
            $fieldCriteria = new Criteria();
            $fieldCriteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_API_RESPONSE));
            $existingField = $customFieldRepository->search($fieldCriteria, $context)->first();
            if ($existingField === null) {
                $customFieldRepository->create([
                    [
                        'name' => self::CUSTOM_FIELD_API_RESPONSE,
                        'type' => CustomFieldTypes::TEXT,
                        'customFieldSetId' => $existingSet->getId(),
                        'config' => [
                            'label' => [
                                'en-GB' => 'Freshdesk API Response',
                                'de-DE' => 'Freshdesk API-Antwort',
                            ],
                            'componentName' => 'sw-field',
                            'customFieldType' => 'text',
                            'customFieldPosition' => 1,
                        ],
                    ],
                ], $context);
            }
        }
    }
}