        foreach ($this->getBlockTypes() as $blockType) {
            $blockTypeId = (string)($blockType->id ?? 'new' . ++$totalNewBlockTypes);
            $blockTypes[$blockTypeId] = $blockType;
            $blockTypeFields[$blockTypeId] = [];
            $totalNewFields = 0;
            $fieldLayout = $blockType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            if (empty($tabs)) {
                continue;
            }
            $tab = $fieldLayout->getTabs()[0];

            foreach ($tab->getElements() as $layoutElement) {
                if ($layoutElement instanceof CustomField) {
                    $field = $layoutElement->getField();

                    // If it's a missing field, swap it with a Text field
                    if ($field instanceof MissingField) {
                        /** @var PlainText $fallback */
                        $fallback = $field->createFallback(PlainText::class);
                        $fallback->addError('type', Craft::t('app', 'The field type “{type}” could not be found.', [
                            'type' => $field->expectedType,
                        ]));
                        $field = $fallback;
                        $layoutElement->setField($field);
                        $blockType->hasFieldErrors = true;
                    }

                    $fieldId = (string)($field->id ?? 'new' . ++$totalNewFields);
                    $blockTypeFields[$blockTypeId][$fieldId] = $layoutElement;

                    if (!$field->getIsNew()) {
                        $fieldTypeOptions[$field->id] = [];
                        $compatibleFieldTypes = $fieldsService->getCompatibleFieldTypes($field, true);
                        foreach ($allFieldTypes as $class) {
                            // No Matrix-Inception, sorry buddy.
                            if ($class !== self::class && ($class === get_class($field) || $class::isSelectable())) {
                                $compatible = in_array($class, $compatibleFieldTypes, true);
                                $fieldTypeOptions[$field->id][] = [
                                    'value' => $class,
                                    'label' => $class::displayName() . ($compatible ? '' : ' ⚠️'),
                                ];
                            }
                        }

                        // Sort them by name
                        ArrayHelper::multisort($fieldTypeOptions[$field->id], 'label');
                    }
                }
            }
        }

        return $view->renderTemplate('_components/fieldtypes/Matrix/settings.twig',
            [
                'matrixField' => $this,
                'fieldTypes' => $fieldTypeOptions,
                'blockTypes' => $blockTypes,
                'blockTypeFields' => $blockTypeFields,
            ]);

        {% for blockTypeId, blockType in blockTypes %}
                    <div data-id="{{ blockTypeId }}">
                        {% for fieldId, layoutElement in blockTypeFields[blockTypeId] %}
                            {% set field = layoutElement.getField() %}

                            {{ forms.selectField({
                                        label: "Field Type"|t('app'),
                                        warning: (not field.getIsNew() and not field.hasErrors('type') ? "Changing this may result in data loss."|t('app')),
                                        id: 'type',
                                        name: 'type',
                                        options: fieldId[0:3] != 'new' ? fieldTypes[fieldId] : fieldTypes.new,
                                        value: className(field),
                                        errors: field.getErrors('type') ?? null
                                    }) }}

        