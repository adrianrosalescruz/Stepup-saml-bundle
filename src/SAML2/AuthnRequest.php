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

namespace Surfnet\SamlBundle\SAML2;

use SAML2_AuthnRequest;
use SAML2_Const;
use Surfnet\SamlBundle\Exception\InvalidArgumentException;
use Surfnet\SamlBundle\Exception\RuntimeException;

class AuthnRequest
{
    const PARAMETER_RELAY_STATE = 'RelayState';
    const PARAMETER_REQUEST = 'SAMLRequest';
    const PARAMETER_SIGNATURE = 'Signature';
    const PARAMETER_SIGNATURE_ALGORITHM = 'SigAlg';

    /**
     * @var null|string
     */
    private $rawHttpQuery;

    /**
     * @var SAML2_AuthnRequest
     */
    private $request;

    /**
     * @var null|string
     */
    private $signature;

    /**
     * @var null|string
     */
    private $signatureAlgorithm;

    /**
     * @param SAML2_AuthnRequest $request
     */
    private function __construct(SAML2_AuthnRequest $request)
    {
        $this->request = $request;
    }

    /**
     * @param SAML2_AuthnRequest $request
     * @param string $relayState
     * @return AuthnRequest
     */
    public static function createUnsigned(
        SAML2_AuthnRequest $request,
        $relayState
    ) {
        $authnRequest = new self($request);
        if ($relayState) {
            $authnRequest->request->setRelayState($relayState);
        }

        return $authnRequest;
    }

    /**
     * @param SAML2_AuthnRequest $request
     * @param string $rawHttpQuery
     * @param string $signature
     * @param string $signatureAlgorithm
     * @param string $relayState
     * @return AuthnRequest
     */
    public static function createSigned(
        SAML2_AuthnRequest $request,
        $rawHttpQuery,
        $signature,
        $signatureAlgorithm,
        $relayState = null
    ) {
        $authnRequest = new self($request);
        $authnRequest->rawHttpQuery = $rawHttpQuery;
        $authnRequest->signature = base64_decode($signature, true);
        $authnRequest->signatureAlgorithm = $signatureAlgorithm;
        if ($relayState !== null) {
            $authnRequest->request->setRelayState($relayState);
        }

        return $authnRequest;
    }

    /**
     * @deprecated use ::createSigned (default) or ::createUnsigned
     * @param SAML2_AuthnRequest $request
     * @param string $rawHttpQuery
     * @param string $signature
     * @param string $signatureAlgorithm
     * @param string $relayState
     * @return AuthnRequest
     */
    public static function create(
        SAML2_AuthnRequest $request,
        $rawHttpQuery,
        $signature,
        $signatureAlgorithm,
        $relayState
    ) {
        return static::createSigned(
            $request,
            $rawHttpQuery,
            $signature,
            $signatureAlgorithm,
            $relayState
        );
    }

    public static function createNew(SAML2_AuthnRequest $req)
    {
        return new self($req);
    }

    /**
     * @return string|null
     */
    public function getAuthenticationContextClassRef()
    {
        $authnContext = $this->request->getRequestedAuthnContext();

        if (!is_array($authnContext) || !array_key_exists('AuthnContextClassRef', $authnContext)) {
            return null;
        }

        return reset($authnContext['AuthnContextClassRef']) ?: null;
    }

    /**
     * @param string $authnClassRef
     */
    public function setAuthenticationContextClassRef($authnClassRef)
    {
        $authnContext = ['AuthnContextClassRef' => [$authnClassRef]];
        $this->request->setRequestedAuthnContext($authnContext);
    }

    /**
     * @return string|null
     */
    public function getNameId()
    {
        $nameId = $this->request->getNameId();
        if (!is_array($nameId) || !array_key_exists('Value', $nameId)) {
            return null;
        }

        return $nameId['Value'];
    }

    /**
     * @return string|null
     */
    public function getNameIdFormat()
    {
        $nameId = $this->request->getNameId();
        if (!is_array($nameId) || !array_key_exists('Format', $nameId)) {
            return null;
        }

        return $nameId['Format'];
    }

    /**
     * @param string      $nameId
     * @param string|null $format
     */
    public function setSubject($nameId, $format = null)
    {
        if (!is_string($nameId)) {
            throw InvalidArgumentException::invalidType('string', 'nameId', $nameId);
        }

        if (!is_null($format) && !is_string($format)) {
            throw InvalidArgumentException::invalidType('string', 'format', $format);
        }

        $nameId = [
            'Value' => $nameId,
            'Format' => ($format ?: SAML2_Const::NAMEID_UNSPECIFIED)
        ];

        $this->request->setNameId($nameId);
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->request->getId();
    }

    /**
     * @return bool
     */
    public function isPassive()
    {
        return $this->request->getIsPassive();
    }

    /**
     * @return bool
     */
    public function isForceAuthn()
    {
        return $this->request->getForceAuthn();
    }

    /**
     * @return bool
     */
    public function isSigned()
    {
        return !empty($this->signature);
    }

    /**
     * @return string
     */
    public function getDestination()
    {
        return $this->request->getDestination();
    }

    /**
     * @return string
     */
    public function getServiceProvider()
    {
        return $this->request->getIssuer();
    }

    /**
     * @return null|string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @return string
     */
    public function getSignatureAlgorithm()
    {
        return $this->signatureAlgorithm;
    }

    /**
     * @return string
     */
    public function getUnsignedXML()
    {
        return $this->request->toUnsignedXML()->ownerDocument->saveXML();
    }

    /**
     * @param array $requesterIds
     * @param int   $proxyCount
     */
    public function setScoping(array $requesterIds, $proxyCount = 10)
    {
        $this->request->setRequesterID($requesterIds);
        $this->request->setProxyCount($proxyCount);
    }

    /**
     * @return string
     */
    public function buildRequestQuery()
    {
        $requestAsXml = $this->getUnsignedXML();
        $encodedRequest = base64_encode(gzdeflate($requestAsXml));
        $queryParams = [self::PARAMETER_REQUEST => $encodedRequest];

        if ($this->request->getRelayState() !== null) {
            $queryParams[self::PARAMETER_RELAY_STATE] = $this->request->getRelayState();
        }

        return $this->signRequestQuery($queryParams);
    }

    /**
     * @return string
     */
    public function getSignedRequestQuery()
    {
        $queryParamPairs = explode('&', $this->rawHttpQuery);
        foreach ($queryParamPairs as $queryParamPair) {
            if (strpos($queryParamPair, '=') === false) {
                throw new RuntimeException('Could not parse signed request query: it does not contain key-value pairs');
            }

            list($key, $value) = explode('=', $queryParamPair);
            $queryParams[$key] = $value;
        }

        if (!isset($queryParams[self::PARAMETER_REQUEST])) {
            throw new RuntimeException(
                sprintf('Could not parse signed request query: it does not contain the key "%s"', self::PARAMETER_REQUEST)
            );
        }

        if (!isset($queryParams[self::PARAMETER_SIGNATURE_ALGORITHM])) {
            throw new RuntimeException(
                sprintf(
                    'Could not parse signed request query: it does not contain the key "%s"',
                    self::PARAMETER_SIGNATURE_ALGORITHM
                )
            );
        }

        $httpQuery = self::PARAMETER_REQUEST . '=' . $queryParams[self::PARAMETER_REQUEST];

        if (isset($queryParams[self::PARAMETER_RELAY_STATE])) {
            $httpQuery .= '&' . self::PARAMETER_RELAY_STATE . '=' . $queryParams[self::PARAMETER_RELAY_STATE];
        }

        $httpQuery .= '&' . self::PARAMETER_SIGNATURE_ALGORITHM . '=' . $queryParams[self::PARAMETER_SIGNATURE_ALGORITHM];

        return $httpQuery;
    }

    /**
     * @param array $queryParams
     * @return string
     */
    private function signRequestQuery(array $queryParams)
    {
        /** @var \XMLSecurityKey $securityKey */
        $securityKey = $this->request->getSignatureKey();
        $queryParams[self::PARAMETER_SIGNATURE_ALGORITHM] = $securityKey->type;

        $toSign = http_build_query($queryParams);
        $signature = $securityKey->signData($toSign);

        $signedQuery = $toSign . '&Signature=' . urlencode(base64_encode($signature));

        return $signedQuery;
    }
}
