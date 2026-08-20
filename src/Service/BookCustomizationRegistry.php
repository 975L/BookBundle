<?php

namespace c975L\BookBundle\Service;

use c975L\BookBundle\Contract\BookCustomizationProviderInterface;
use c975L\BookBundle\Enum\BookEditionKind;
use c975L\ConfigBundle\Management\ProviderMerger;

// Merges what every site declares about its catalog (see BookCustomizationProviderInterface), so the forms and the CRUD read one vocabulary whether the app customizes anything or not
class BookCustomizationRegistry
{
    /** @param iterable<BookCustomizationProviderInterface> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    /** @return array<string, string> kind => label */
    public function getMediaKinds(): array
    {
        return ProviderMerger::merge($this->providers, static fn (BookCustomizationProviderInterface $provider) => $provider->getMediaKinds());
    }

    // The bundle's own paper/digital/audio when the site names no edition of its own, which is what a catalog holds until it publishes an illustrated or a translated one
    /** @return array<string, string> kind => label */
    public function getEditionKinds(): array
    {
        $kinds = ProviderMerger::merge($this->providers, static fn (BookCustomizationProviderInterface $provider) => $provider->getEditionKinds());

        return [] === $kinds ? BookEditionKind::defaults() : $kinds;
    }

    // The first form type declared, an app having one set of its own book fields and not one per provider
    /** @return class-string|null */
    public function getDataFormType(): ?string
    {
        foreach ($this->providers as $provider) {
            $formType = $provider->getDataFormType();

            if (null !== $formType) {
                return $formType;
            }
        }

        return null;
    }
}
