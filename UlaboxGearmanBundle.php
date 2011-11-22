<?php

namespace Ulabox\GearmanBundle;

use Symfony\Component\Config\FileLocator;
use Ulabox\GearmanBundle\Module\GearmanBaseBundle;
use Ulabox\GearmanBundle\Module\WorksDirectoryLoader;
use Ulabox\GearmanBundle\Module\WorkerClass as Worker;
use Ulabox\GearmanBundle\Module\WorkerCollection;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Ulabox\GearmanBundle\Module\GearmanCache as Cache;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;

/**
 * Gearman Bundle
 *
 * @author Marc Morera <marc@ulabox.com>
 */
class UlaboxGearmanBundle extends GearmanBaseBundle
{
    /**
     * Boots the Bundle.
     * This method load all data and saves all annotations into cache.
     * Also, it load all settings from Yaml file format
     *
     * @api
     */
    function boot(){

        
        $rootDir = $this->container->get('kernel')->getRootDir();
        $cachedir = $rootDir . '/cache/'.$this->container->get('kernel')->getEnvironment().'/Gearman/';

        $gearmanCache = new Cache($cachedir);

        $settingsPath = $rootDir . '/config/gearman_'.$this->container->get('kernel')->getEnvironment().'.yml';
        $this->loadSettings($settingsPath);
        $existsCache = $gearmanCache->existsCacheFile();

        if (in_array($this->container->get('kernel')->getEnvironment(), array('dev', 'test')) || !$existsCache) {

            if ($existsCache) {
                $gearmanCache->emptyCache();
            }
            $reader = new AnnotationReader();
            AnnotationRegistry::registerFile(__DIR__ . "/Driver/Gearman/GearmanAnnotations.php");
            $reader->setDefaultAnnotationNamespace('Ulabox\GearmanBundle\Driver\\');
            $workerCollection = new WorkerCollection;
            $bundles = $this->container->get('kernel')->getBundles();
            foreach ($bundles as $bundle) {
                if (!\in_array($bundle->getNamespace(), $this->getParseableBundles())) {
                    continue;
                }

                $filesLoader = new WorksDirectoryLoader(new FileLocator('.'));
                $files = $filesLoader->load($bundle->getPath());

                foreach ($files as $file) {
                    $reflClass = new \ReflectionClass($file['class']);
                    $classAnnotations = $reader->getClassAnnotations($reflClass);

                    foreach ($classAnnotations as $annot) {

                        if ($annot instanceof \Ulabox\GearmanBundle\Driver\Gearman\Work) {
                            $workerCollection->add(New Worker($annot, $reflClass, $reader, $this->getSettings()));
                        }
                    }
                }
            }

            $gearmanCache   ->set($workerCollection->__toCache())
                            ->save();
        }
    }
}