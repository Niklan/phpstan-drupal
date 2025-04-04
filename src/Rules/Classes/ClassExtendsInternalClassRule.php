<?php declare(strict_types=1);

namespace mglaman\PHPStanDrupal\Rules\Classes;

use mglaman\PHPStanDrupal\Internal\NamespaceCheck;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function sprintf;

/**
 * @implements Rule<Class_>
 */
class ClassExtendsInternalClassRule implements Rule
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!isset($node->extends)) {
            return [];
        }

        $extendedClassName = $node->extends->toString();
        if (!$this->reflectionProvider->hasClass($extendedClassName)) {
            return [];
        }

        $extendedClassReflection = $this->reflectionProvider->getClass($extendedClassName);
        if (!$extendedClassReflection->isInternal()) {
            return [];
        }

        if (!isset($node->namespacedName)) {
            return $this->buildError(null, $extendedClassName, null);
        }

        $currentClassName = $node->namespacedName->toString();

        if (!NamespaceCheck::isDrupalNamespace($node)) {
            return $this->buildError($currentClassName, $extendedClassName, null);
        }

        if (NamespaceCheck::isSharedNamespace($node)) {
            return [];
        }

        $tip = null;
        if ($extendedClassName === 'Drupal\Core\Entity\ContentEntityDeleteForm') {
            $tip = 'Extend \Drupal\Core\Entity\ContentEntityConfirmFormBase. See https://www.drupal.org/node/2491057';
        } elseif ((string) $node->extends->slice(0, 2) === 'Drupal\Core') {
            $tip = 'Read the Drupal core backwards compatibility and internal API policy: https://www.drupal.org/about/core/policies/core-change-policies/drupal-8-and-9-backwards-compatibility-and-internal-api#internal';
        }

        return $this->buildError(
            $currentClassName,
            $extendedClassName,
            $tip
        );
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function buildError(?string $currentClassName, string $extendedClassName, ?string $tip): array
    {
        $ruleErrorBuilder = RuleErrorBuilder::message(sprintf(
            '%s extends @internal class %s.',
            $currentClassName !== null ? sprintf('Class %s', $currentClassName) : 'Anonymous class',
            $extendedClassName
        ))->identifier('classExtendsInternalClass.classExtendsInternalClass');
        if ($tip !== null) {
            $ruleErrorBuilder->tip($tip);
        }

        return [$ruleErrorBuilder->build()];
    }
}
