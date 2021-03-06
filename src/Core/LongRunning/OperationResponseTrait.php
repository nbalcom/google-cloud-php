<?php
/**
 * Copyright 2016 Google Inc.
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

namespace Google\Cloud\Core\LongRunning;

use DrSlump\Protobuf\CodecInterface;
use Google\GAX\OperationResponse;

/**
 * Serializes and deserializes GAX LRO Response objects.
 *
 * This trait should be used in a gRPC Connection class to normalize responses.
 */
trait OperationResponseTrait
{
    /**
     * Convert a GAX OperationResponse object to an array.
     *
     * @param OperationResponse $operation The operation response
     * @param CodecInterface $codec The codec to use for gRPC serialization/deserialization.
     * @param array $lroMappers A list of mappers for deserializing operation results.
     * @return array
     */
    private function operationToArray(OperationResponse $operation, CodecInterface $codec, array $lroMappers)
    {
        $response = $operation->getLastProtoResponse();
        if (is_null($response)) {
            return null;
        }

        $response = $response->serialize($codec);

        $result = null;
        if ($operation->isDone()) {
            $type = $response['metadata']['typeUrl'];
            $result = $this->deserializeResult($operation, $type, $codec, $lroMappers);
        }

        $error = $operation->getError();
        if (!is_null($error)) {
            $error = $error->serialize($codec);
        }

        $response['response'] = $result;
        $response['error'] = $error;

        return $response;
    }

    /**
     * Fetch an OperationResponse object from a gapic client.
     *
     * @param mixed $client A generated client with a `resumeOperation` method.
     * @param string $name The Operation name.
     * @param string|null $method The method name.
     * @return OperationResponse
     */
    private function getOperationByName($client, $name, $method = null)
    {
        return $client->resumeOperation($name, $method);
    }

    /**
     * Convert an operation response to an array
     *
     * @param OperationResponse $operation The operation to serialize.
     * @param string $type The Operation type. The type should correspond to a
     *        member of $mappers.typeUrl.
     * @param CodecInterface $codec The gRPC codec to use for the deserialization.
     * @param array $mappers A list of mappers.
     * @return array|null
     */
    private function deserializeResult(OperationResponse $operation, $type, CodecInterface $codec, array $mappers)
    {
        $mappers = array_filter($mappers, function ($mapper) use ($type) {
            return $mapper['typeUrl'] === $type;
        });

        if (count($mappers) === 0) {
            throw new \RuntimeException(sprintf('No mapper exists for operation response type %s.', $type));
        }

        $mapper = current($mappers);
        $message = $mapper['message'];

        $response = new $message();
        $anyResponse = $operation->getLastProtoResponse()->getResponse();

        if (is_null($anyResponse)) {
            return null;
        }

        $response->parse($anyResponse->getValue());

        return $response->serialize($codec);
    }
}
