<?php

namespace PHPCR\Shell\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use PHPCR\NodeInterface;
use PHPCR\PropertyType;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use PHPCR\PropertyInterface;

/**
 * Normalizer for PHPCR Nodes
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class NodeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    protected $allowBinary;
    protected $notes = array();

    public function __construct($allowBinary = false)
    {
        $this->allowBinary = $allowBinary;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * {@inheritDoc}
     */
    public function normalize($node, $format = null, array $context = array())
    {
        $res = array();

        foreach ($node->getProperties() as $property) {
            if (false === $this->isPropertyEditable($property)) {
                continue;
            }

            $propertyType = $property->getType();
            $propertyValue = $property->getValue();
            $propertyName = $property->getName();

            $res[$propertyName] = array(
                'type' => PropertyType::nameFromValue($propertyType),
                'value' => $propertyValue
            );
        }

        return $res;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && $data instanceof NodeInterface;
    }

    /**
     * {@inheritDoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (!$data) {
            throw new \InvalidArgumentException(
                'Editor returned nothing .. nodes must have at least one property (i.e. the jcr:primaryType property)'
            );
        }

        if (!isset($context['node'])) {
            throw new \InvalidArgumentException(sprintf(
                'You must provide the PHPCR node instance to update in the context using the "node" key.'
            ));
        }

        $node = $context['node'];

        $errors = array();

        // Update / remove existing properties
        foreach ($node->getProperties() as $property) {
            if (false === $this->isPropertyEditable($property)) {
                continue;
            }

            try {

                if (!isset($data[$property->getName()])) {
                    $property->remove();
                    continue;
                }

                $datum = $this->normalizeDatum($data[$property->getName()]);

                if (isset($datum['type'])) {
                    $typeName = $datum['type'];

                    if ($datum['type'] != $typeName) {
                        throw new \InvalidArgumentException(sprintf(
                            'Cannot currently change a properties type for property "%s" (trying to change from "%s" to "%s")',
                            $property->getPath(),
                            $typeName, $datum['type']
                        ));
                    }
                }

                if (isset($datum['value'])) {
                    if ($datum['value'] != $property->getValue()) {
                        $property->setValue($datum['value']);
                    }
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }

            unset($data[$property->getName()]);
        }

        // Add new properties
        foreach ($data as $pName => $datum) {
            $datum = $this->normalizeDatum($datum);
            $pValue = isset($datum['value']) ? $datum['value'] : null;
            $pType = isset($datum['type']) ? PropertyType::valueFromName($datum['type']) : null;

            if ($pValue !== null) {
                $node->setProperty($pName, $pValue, $pType);
            }
        }

        if (count($errors) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Errors encountered during denormalization: %s',
                implode($errors, "\n")
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $type === 'PHPCR\NodeInterface';
    }

    /**
     * If the value is a scalar value convert it into
     * an array with default values
     *
     * @param mixed
     *
     * @return string
     */
    private function normalizeDatum($value)
    {
        if (is_scalar($value)) {
            return array(
                'value' => $value,
                'type' => 'String'
            );
        }

        return $value;
    }

    /**
     * Return false if property type is not editable
     *
     * (e.g. property type is binary)
     *
     * @return boolean
     */
    private function isPropertyEditable(PropertyInterface $property)
    {
        // do not serialize binary objects
        if (false === $this->allowBinary && PropertyType::BINARY == $property->getType()) {
            $this->notes[] = sprintf(
                'Binary property "%s" has been omitted', $property->getName()
            );
            return false;
        }

        return true;
    }
}
