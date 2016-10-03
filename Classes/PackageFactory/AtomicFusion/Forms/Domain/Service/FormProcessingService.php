<?php
namespace PackageFactory\AtomicFusion\Forms\Domain\Service;

/**
 * This file is part of the PackageFactory.AtomicFusion.Forms package
 *
 * (c) 2016 Wilhelm Behncke <wilhelm.behncke@googlemail.com>
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Error\Result;
use TYPO3\Flow\Error\Error;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Flow\Property\PropertyMappingConfiguration;
use TYPO3\Flow\Property\PropertyMapper;
use TYPO3\Flow\Validation\Validator\ConjunctionValidator;
use TYPO3\Flow\Validation\ValidatorResolver;
use TYPO3\TypoScript\Core\Runtime;

/**
 * @Flow\Scope("singleton")
 */
class FormProcessingService
{
	/**
     * @Flow\Inject
     * @var PropertyMapper
     */
    protected $propertyMapper;

	/**
     * @Flow\Inject
     * @var ValidatorResolver
     */
    protected $validatorResolver;

	public function process(FormContext $formContext)
	{
		$result =  new Result();
		$fieldConfiguration = $formContext->getFieldConfiguration();

		foreach ($fieldConfiguration as $fieldName => $configuration) {
			if (isset($configuration['page']) && !$formContext->getFormState()->isCurrentPage($configuration['page'])) {
				continue;
			}
			$propertyMappingConfiguration = new PropertyMappingConfiguration();
			$conjunctionValidator = new ConjunctionValidator();
			$value = $formContext->getFieldValueForPath($fieldName);

			if ($type = Arrays::getValueByPath($configuration, 'type')) {
				$value = $this->propertyMapper->convert($value, $type, $propertyMappingConfiguration);
	            $result->forProperty($fieldName)->merge($this->propertyMapper->getMessages());
			}

			foreach ($configuration['validators'] as $validator) {
				$conjunctionValidator->addValidator($validator);
			}

			$result->forProperty($fieldName)->merge($conjunctionValidator->validate($value));
		}

		return $result;
	}
}
