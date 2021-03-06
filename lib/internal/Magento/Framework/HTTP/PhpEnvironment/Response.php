<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Framework\HTTP\PhpEnvironment;

/**
 * Base HTTP response object
 */
class Response extends \Zend\Http\PhpEnvironment\Response implements \Magento\Framework\App\Response\HttpInterface
{
    /**
     * Flag; is this response a redirect?
     *
     * @var boolean
     */
    protected $isRedirect = false;

    /**
     * @var bool
     */
    private $headersSent;

    /**
     * @inheritdoc
     */
    public function getHeader($name)
    {
        $header = false;
        $headers = $this->getHeaders();
        if ($headers->has($name)) {
            $header = $headers->get($name);
            // zend-http >= 2.10.11 can return \ArrayIterator instead of a single Header
            if ($header instanceof \ArrayIterator) {
                $header = $header->current();
            }
        }
        return $header;
    }

    /**
     * Send the response, including all headers, rendering exceptions if so requested.
     *
     * @return void
     */
    public function sendResponse()
    {
        $this->send();
    }

    /**
     * @inheritdoc
     */
    public function appendBody($value)
    {
        $body = $this->getContent();
        $this->setContent($body . $value);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setBody($value)
    {
        $this->setContent($value);
        return $this;
    }

    /**
     * Clear body
     *
     * @return $this
     */
    public function clearBody()
    {
        $this->setContent('');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setHeader($name, $value, $replace = false)
    {
        $value = (string)$value;

        if ($replace) {
            $this->clearHeader($name);
        }

        $this->getHeaders()->addHeaderLine($name, $value);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function clearHeader($name)
    {
        $headers = $this->getHeaders();
        if ($headers->has($name)) {
            $header = $headers->get($name);
            $headers->removeHeader($header);
        }

        return $this;
    }

    /**
     * Remove all headers
     *
     * @return $this
     */
    public function clearHeaders()
    {
        $headers = $this->getHeaders();
        $headers->clearHeaders();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setRedirect($url, $code = 302)
    {
        $this->setHeader('Location', $url, true)
            ->setHttpResponseCode($code);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setHttpResponseCode($code)
    {
        if (!is_numeric($code) || (100 > $code) || (599 < $code)) {
            throw new \InvalidArgumentException('Invalid HTTP response code');
        }

        $this->isRedirect = (300 <= $code && 307 >= $code);

        $this->setStatusCode($code);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setStatusHeader($httpCode, $version = null, $phrase = null)
    {
        $version = $version === null ? $this->detectVersion() : $version;
        $phrase = $phrase === null ? $this->getReasonPhrase() : $phrase;

        $this->setVersion($version);
        $this->setHttpResponseCode($httpCode);
        $this->setReasonPhrase($phrase);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getHttpResponseCode()
    {
        return $this->getStatusCode();
    }

    /**
     * Is this a redirect?
     *
     * @return boolean
     */
    public function isRedirect()
    {
        return $this->isRedirect;
    }

    /**
     * @inheritDoc
     *
     * @return string[]
     * @SuppressWarnings(PHPMD.SerializationAware)
     */
    public function __sleep()
    {
        return ['content', 'isRedirect', 'statusCode'];
    }

    /**
     * Sending provided headers.
     *
     * Had to be overridden because the original did not work correctly with multi-headers.
     */
    public function sendHeaders()
    {
        if ($this->headersSent()) {
            return $this;
        }

        $status  = $this->renderStatusLine();
        header($status);

        /** @var \Zend\Http\Header\HeaderInterface $header */
        foreach ($this->getHeaders() as $header) {
            header($header->toString(), false);
        }

        $this->headersSent = true;
        return $this;
    }
}
