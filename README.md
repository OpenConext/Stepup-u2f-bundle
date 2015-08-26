# Step-up U2fBundle
[![Build Status](https://travis-ci.org/SURFnet/Stepup-u2f-bundle.svg)](https://travis-ci.org/SURFnet/Stepup-u2f-bundle) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SURFnet/Stepup-u2f-bundle/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/SURFnet/Stepup-u2f-bundle/?branch=develop)

The SURFnet Step-up U2F Bundle contains server-side device verification, and the necessary forms and resources to enable client-side U2F interaction with Step-up Identities

## Installation

 * Add the package to your Composer file
    ```sh
    composer require surfnet/stepup-u2f-bundle
    ```

 * Add the bundle to your kernel in `app/AppKernel.php`
    ```php
    public function registerBundles()
    {
        // ...
        $bundles[] = new Surfnet\StepupU2fBundle\SurfnetStepupU2fBundle;
    }
    ```