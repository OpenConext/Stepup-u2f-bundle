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

namespace Surfnet\StepupU2fBundle\Tests\Service;

use Mockery as m;
use PHPUnit_Framework_TestCase as TestCase;
use Surfnet\StepupU2fBundle\Dto\RegisterRequest;
use Surfnet\StepupU2fBundle\Dto\RegisterResponse;
use Surfnet\StepupU2fBundle\Dto\Registration;
use Surfnet\StepupU2fBundle\Service\RegistrationVerificationResult;
use Surfnet\StepupU2fBundle\Service\U2fService;
use Surfnet\StepupU2fBundle\Value\AppId;
use u2flib_server\Error;
use u2flib_server\RegisterRequest as YubicoRegisterRequest;
use u2flib_server\Registration as YubicoRegistration;

/**
 * These tests also assert that DTO mapping takes place correctly, serving as a smoke test. Moving these to a separate
 * test case would only introduce a lot of test duplication.
 */
final class U2fServiceRegistrationTest extends TestCase
{
    const APP_ID = 'https://gateway.surfconext.invalid/u2f/app-id';

    /**
     * @test
     * @group registration
     */
    public function it_can_create_a_registration_request()
    {
        $yubicoRequest = new YubicoRegisterRequest('challenge', self::APP_ID);

        $u2f = m::mock('u2flib_server\U2F');
        $u2f->shouldReceive('getRegisterData')->once()->with()->andReturn([$yubicoRequest, []]);

        $service = new U2fService(new AppId(self::APP_ID), $u2f);

        $expectedRequest            = new RegisterRequest();
        $expectedRequest->version   = 'U2F_V2';
        $expectedRequest->challenge = 'challenge';
        $expectedRequest->appId     = self::APP_ID;

        $this->assertEquals($expectedRequest, $service->createRegistrationRequest());
    }

    /**
     * @test
     * @group registration
     */
    public function it_can_register_a_u2f_device()
    {
        $publicId  = 'public-key';
        $keyHandle = 'key-handle';

        $yubicoRequest = new YubicoRegisterRequest('challenge', self::APP_ID);

        $yubicoRegistration              = new YubicoRegistration();
        $yubicoRegistration->publicKey   = $publicId;
        $yubicoRegistration->keyHandle   = $keyHandle;
        $yubicoRegistration->certificate = 'certificate';
        $yubicoRegistration->counter     = 0;

        $request            = new RegisterRequest();
        $request->version   = 'U2F_V2';
        $request->challenge = 'challenge';
        $request->appId     = self::APP_ID;

        $response                   = new RegisterResponse();
        $response->registrationData = 'registration-data';
        $response->clientData       = 'client-data';

        $yubicoResponse                   = new \stdClass;
        $yubicoResponse->clientData       = $response->clientData;
        $yubicoResponse->registrationData = $response->registrationData;

        $expectedRegistration            = new Registration();
        $expectedRegistration->publicKey = $publicId;
        $expectedRegistration->keyHandle = $keyHandle;

        $expectedResult = RegistrationVerificationResult::success($expectedRegistration);

        $u2f = m::mock('u2flib_server\U2F');
        $u2f->shouldReceive('doRegister')
            ->once()
            ->with(m::anyOf($yubicoRequest), m::anyOf($yubicoResponse))
            ->andReturn($yubicoRegistration);

        $service = new U2fService(new AppId(self::APP_ID), $u2f);

        $this->assertEquals($expectedResult, $service->verifyRegistration($request, $response));
    }

    /**
     * @test
     * @group registration
     * @dataProvider expectedVerificationErrors
     *
     * @param int $errorCode
     * @param RegistrationVerificationResult $expectedResult
     */
    public function it_handles_expected_u2f_registration_verification_errors(
        $errorCode,
        RegistrationVerificationResult $expectedResult
    ) {
        $yubicoRequest = new YubicoRegisterRequest('challenge', self::APP_ID);

        $request            = new RegisterRequest();
        $request->version   = 'U2F_V2';
        $request->challenge = 'challenge';
        $request->appId     = self::APP_ID;

        $response                   = new RegisterResponse();
        $response->registrationData = 'registration-data';
        $response->clientData       = 'client-data';

        $yubicoResponse                   = new \stdClass;
        $yubicoResponse->clientData       = $response->clientData;
        $yubicoResponse->registrationData = $response->registrationData;

        $u2f = m::mock('u2flib_server\U2F');
        $u2f->shouldReceive('doRegister')
            ->once()
            ->with(m::anyOf($yubicoRequest), m::anyOf($yubicoResponse))
            ->andThrow(new Error('error', $errorCode));

        $service = new U2fService(new AppId(self::APP_ID), $u2f);

        $this->assertEquals($expectedResult, $service->verifyRegistration($request, $response));
    }

    public function expectedVerificationErrors()
    {
        // Autoload the U2F class to make sure the error constants are loaded which are also defined in the file.
        class_exists('u2flib_server\U2F');

        return [
            'responseChallengeDidNotMatchRequestChallenge' => [
                \u2flib_server\ERR_UNMATCHED_CHALLENGE,
                RegistrationVerificationResult::responseChallengeDidNotMatchRequestChallenge()
            ],
            'responseWasNotSignedByDevice' => [
                \u2flib_server\ERR_ATTESTATION_SIGNATURE,
                RegistrationVerificationResult::responseWasNotSignedByDevice()
            ],
            'deviceCannotBeTrusted' => [
                \u2flib_server\ERR_ATTESTATION_VERIFICATION,
                RegistrationVerificationResult::deviceCannotBeTrusted()
            ],
            'publicKeyDecodingFailed' => [
                \u2flib_server\ERR_PUBKEY_DECODE,
                RegistrationVerificationResult::publicKeyDecodingFailed()
            ],
        ];
    }

    /**
     * @test
     * @group registration
     * @dataProvider unexpectedVerificationErrors
     * @expectedException \Surfnet\StepupU2fBundle\Exception\LogicException
     *
     * @param int $errorCode
     */
    public function it_throws_unexpected_u2f_registration_verification_errors($errorCode)
    {
        $yubicoRequest = new YubicoRegisterRequest('challenge', self::APP_ID);

        $request            = new RegisterRequest();
        $request->version   = 'U2F_V2';
        $request->challenge = 'challenge';
        $request->appId     = self::APP_ID;

        $response                   = new RegisterResponse();
        $response->registrationData = 'registration-data';
        $response->clientData       = 'client-data';

        $yubicoResponse                   = new \stdClass;
        $yubicoResponse->clientData       = $response->clientData;
        $yubicoResponse->registrationData = $response->registrationData;

        $u2f = m::mock('u2flib_server\U2F');
        $u2f->shouldReceive('doRegister')
            ->once()
            ->with(m::anyOf($yubicoRequest), m::anyOf($yubicoResponse))
            ->andThrow(new Error('error', $errorCode));

        $service = new U2fService(new AppId(self::APP_ID), $u2f);
        $service->verifyRegistration($request, $response);
    }

    public function unexpectedVerificationErrors()
    {
        // Autoload the U2F class to make sure the error constants are loaded which are also defined in the file.
        class_exists('u2flib_server\U2F');

        return [
            [\u2flib_server\ERR_AUTHENTICATION_FAILURE],
            [\u2flib_server\ERR_BAD_RANDOM],
            [235789],
        ];
    }

    /**
     * @test
     * @group registration
     * @dataProvider deviceErrorCodes
     *
     * @param int $deviceErrorCode
     * @param string $errorMethod
     */
    public function it_handles_device_errors($deviceErrorCode, $errorMethod)
    {
        $request            = new RegisterRequest();
        $request->version   = 'U2F_V2';
        $request->challenge = 'challenge';
        $request->appId     = self::APP_ID;

        $response            = new RegisterResponse();
        $response->errorCode = $deviceErrorCode;

        $service = new U2fService(new AppId(self::APP_ID), m::mock('u2flib_server\U2F'));
        $result = $service->verifyRegistration($request, $response);

        $this->assertTrue($result->$errorMethod(), "Registration result should report $errorMethod() to be true");
    }

    public function deviceErrorCodes()
    {
        return [
            'didDeviceReportABadRequest' => [
                RegisterResponse::ERROR_CODE_BAD_REQUEST,
                'didDeviceReportABadRequest',
            ],
            'wasClientConfigurationUnsupported' => [
                RegisterResponse::ERROR_CODE_CONFIGURATION_UNSUPPORTED,
                'wasClientConfigurationUnsupported',
            ],
            'wasDeviceAlreadyRegistered' => [
                RegisterResponse::ERROR_CODE_DEVICE_INELIGIBLE,
                'wasDeviceAlreadyRegistered',
            ],
            'didDeviceTimeOut' => [
                RegisterResponse::ERROR_CODE_TIMEOUT,
                'didDeviceTimeOut',
            ],
            'didDeviceReportAnUnknownError' => [
                RegisterResponse::ERROR_CODE_OTHER_ERROR,
                'didDeviceReportAnUnknownError',
            ],
        ];
    }

    /**
     * @test
     * @group registration
     */
    public function it_rejects_the_registration_when_app_ids_dont_match()
    {
        $request            = new RegisterRequest();
        $request->version   = 'U2F_V2';
        $request->challenge = 'challenge';
        $request->appId     = 'https://hacker.invalid/epp-aai-die';

        $response                   = new RegisterResponse();
        $response->registrationData = 'registration-data';
        $response->clientData       = 'client-data';

        $service = new U2fService(new AppId(self::APP_ID), m::mock('u2flib_server\U2F'));

        $this->assertEquals(
            RegistrationVerificationResult::appIdMismatch(),
            $service->verifyRegistration($request, $response)
        );
    }
}
