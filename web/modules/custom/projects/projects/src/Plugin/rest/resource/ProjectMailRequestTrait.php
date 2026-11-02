<?php

namespace Drupal\projects\Plugin\rest\resource;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the optional mail of a decline or archive request.
 */
trait ProjectMailRequestTrait {

  /**
   * The maximum length of the subject.
   */
  protected const int SUBJECT_MAX_LENGTH = 255;

  /**
   * The maximum length of the body.
   */
  protected const int BODY_MAX_LENGTH = 10000;

  /**
   * Gets the subject and body of the request, or NULL if there is no mail.
   *
   * @return array{0: string, 1: string}|null
   *   The subject and the body.
   */
  protected function mail(array $content): ?array {
    $subject = $content['subject'] ?? NULL;
    $body = $content['body'] ?? NULL;
    if ($subject === NULL && $body === NULL) {
      return NULL;
    }
    if (!is_string($subject) || !is_string($body) || trim($subject) === '' || trim($body) === '') {
      throw new BadRequestHttpException('The mail needs both a subject and a body.');
    }
    if (mb_strlen($subject) > static::SUBJECT_MAX_LENGTH || mb_strlen($body) > static::BODY_MAX_LENGTH) {
      throw new BadRequestHttpException('The subject or the body of the mail is too long.');
    }
    return [$subject, $body];
  }

}
