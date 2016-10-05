<?php
namespace PackageFactory\AtomicFusion\Forms\Fusion;

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
use TYPO3\TypoScript\TypoScriptObjects\AbstractTypoScriptObject;
use PackageFactory\AtomicFusion\Forms\Domain\Service\FormContext;
use PackageFactory\AtomicFusion\Forms\Domain\Service\FormProcessingService;
use PackageFactory\AtomicFusion\Forms\Service\FormAugmentationService;
use PackageFactory\AtomicFusion\Forms\Service\HiddenInputTagMappingService;

class FormImplementation extends AbstractTypoScriptObject
{
	/**
	 * @Flow\Inject
	 * @var FormAugmentationService
	 */
	protected $formAugmentationService;

	/**
	 * @Flow\Inject
	 * @var HiddenInputTagMappingService
	 */
	protected $hiddenInputTagMappingService;

	/**
	 * @Flow\Inject
	 * @var FormProcessingService
	 */
	protected $formProcessingService;

	public function evaluate()
	{
		//
		// Create Form context with subrequest
		//
		$request = $this->tsRuntime->getControllerContext()->getRequest();
		$fields = $this->tsValue('fields');
		$finishers = $this->tsValue('finishers');
		$pages = $this->tsValue('pages');
		$action = $this->tsValue('action');
		$formContext = new FormContext($this->path, $action, $fields, $finishers, $request);

		$currentPage = $formContext->getFormState()->getCurrentPage();

		$this->tsRuntime->pushContextArray($this->tsRuntime->getCurrentContext() + [
			$this->tsValue('formContext') => $formContext
		]);

		if (!$formContext->getFormState()->isInitialCall()) {
			$result = $this->formProcessingService->process($formContext, $this->tsRuntime);

			if (!$result->hasErrors()) {
				if ($nextPage = $pages->getNextPage($currentPage)) {
					//
					// Turn page
					//
					$formContext->persistRequestArguments();
					$formContext->getFormState()->setCurrentPage($nextPage);
					$currentPage = $nextPage;
				} else {
					//
					// Run finisher
					//
					$stringResult = null;

					foreach ($finishers as $finisher) {
						if ($finisherResult = $finisher->execute()) {
							$stringResult = $finisherResult;
						}
					}

					if ($stringResult !== null) {
						return $stringResult;
						$this->tsRuntime->popContext();
					}
				}
			}

			$formContext->setValidationResult($result);
		} else {
			if (!$this->tsRuntime->canRender(sprintf('%s/renderer', $this->path))) {
				$currentPage = $pages->getInitialPage();
			}
			$formContext->getFormState()->setCurrentPage($currentPage);
		}

		//
		// Render
		//
		if ($currentPage === null) {
			$renderedForm = $this->tsRuntime->render(sprintf('%s/renderer', $this->path));
		} else {
			$renderedForm = $pages->renderPage($currentPage);
		}

		$this->tsRuntime->popContext();

		//
		// Augment rendering result with form meta information
		//
		// - Form State
		// - Trusted Properties
		//
		return $this->formAugmentationService->injectStringAfterOpeningFormTag(
			$renderedForm,
			sprintf(
				'<div style="display: none;">%s</div>',
				$this->hiddenInputTagMappingService->convertFlatMapToHiddenInputTags([
					'__state' => $formContext->getEncodedFormState()
				], $formContext->getArgumentNamespace())
			)
		);
	}
}
