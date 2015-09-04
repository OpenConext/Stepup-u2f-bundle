<?php

/**
 * Copyright 2014 SURFnet bv
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Surfnet\StepupU2fBundle\Tests\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit_Framework_TestCase as TestCase;
use Surfnet\StepupU2fBundle\DependencyInjection\Configuration;
use Surfnet\StepupU2fBundle\Value\AppId;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    /**
     * @test
     * @group bundle
     * @dataProvider validAppIds
     *
     * @param string $appId
     */
    public function it_processes_a_valid_app_id($appId)
    {
        $this->assertProcessedConfigurationEquals([['app_id' => $appId]], ['app_id' => new AppId($appId)]);
    }

    public function validAppIds()
    {
        return [
            'AppID without path' => ['https://gateway.surfconext.invalid'],
            'AppID with root path' => ['https://gateway.surfconext.invalid/'],
            'AppID with path' => ['https://gateway.surfconext.invalid/u2f-app-id'],
        ];
    }

    /**
     * @test
     * @group bundle
     * @dataProvider invalidAppIds
     *
     * @param mixed $appId
     * @param string $partOfExpectedMessage
     */
    public function it_rejects_an_invalid_app_id($appId, $partOfExpectedMessage)
    {
        $this->assertConfigurationIsInvalid([['app_id' => $appId]], $partOfExpectedMessage);
    }

    public function invalidAppIds()
    {
        return [
            'AppID over HTTP' => ['http://gateway.surfconext.invalid', 'must be "https"'],
            'AppID over FTP' => ['ftp://gateway.surfconext.invalid', 'must be "https"'],
            'integer' => [1, 'should be of type "string"'],
            'null' => [null, 'should be of type "string"'],
            'empty string' => ['', 'must be "https"'],
            'object' => [new \stdClass, 'should be of type "string"'],
            'array' => [array(), 'should be of type "string"'],
        ];
    }

    protected function getConfiguration()
    {
        return new Configuration();
    }
}
