<?php

declare(strict_types=1);

namespace UupCode\Utilities;

/**
 * wp_mail wrapper — fluent builder.
 *
 * Usage:
 *
 *   Mail::to('user@example.com')
 *       ->subject('Your order is confirmed')
 *       ->html('<p>Thanks for your purchase!</p>')
 *       ->send();
 *
 *   Mail::to(['a@example.com', 'b@example.com'])
 *       ->subject('Newsletter')
 *       ->text('Plain text body')
 *       ->from('noreply@mysite.com', 'My Site')
 *       ->replyTo('support@mysite.com')
 *       ->attach('/path/to/invoice.pdf')
 *       ->send();
 */
final class Mail
{
    /** @var string[] */
    private array  $to          = [];
    private string $subject     = '';
    private string $body        = '';
    private bool   $isHtml      = false;
    private string $fromAddress = '';
    private string $fromName    = '';
    private string $replyTo     = '';
    /** @var string[] */
    private array  $attachments = [];
    /** @var array<string, string> */
    private array  $headers     = [];

    private function __construct()
    {
    }

    /**
     * Start building a new mail message.
     *
     * @param string|string[] $to
     */
    public static function to(string|array $to): static
    {
        $instance     = new static();
        $instance->to = (array) $to;
        return $instance;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $body): static
    {
        $this->body   = $body;
        $this->isHtml = true;
        return $this;
    }

    public function text(string $body): static
    {
        $this->body   = $body;
        $this->isHtml = false;
        return $this;
    }

    public function from(string $email, string $name = ''): static
    {
        $this->fromAddress = $email;
        $this->fromName    = $name;
        return $this;
    }

    public function replyTo(string $email): static
    {
        $this->replyTo = $email;
        return $this;
    }

    public function attach(string $filePath): static
    {
        $this->attachments[] = $filePath;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[ $name ] = $value;
        return $this;
    }

    /**
     * Send the email and return whether wp_mail succeeded.
     */
    public function send(): bool
    {
        $headers = $this->buildHeaders();

        return wp_mail(
            $this->to,
            $this->subject,
            $this->body,
            $headers,
            $this->attachments,
        );
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function buildHeaders(): array
    {
        $headers = [];

        if ($this->isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        if ($this->fromAddress !== '') {
            $from      = $this->fromName !== ''
                ? $this->fromName . ' <' . $this->fromAddress . '>'
                : $this->fromAddress;
            $headers[] = 'From: ' . $from;
        }

        if ($this->replyTo !== '') {
            $headers[] = 'Reply-To: ' . $this->replyTo;
        }

        foreach ($this->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        return $headers;
    }
}
