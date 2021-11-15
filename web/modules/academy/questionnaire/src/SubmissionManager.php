<?php

namespace Drupal\questionnaire;

use Drupal\academy\AcademicFormatInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\questionnaire\Entity\Question;
use Drupal\questionnaire\Entity\QuestionSubmission;
use Psr\Log\LoggerInterface;

/**
 * Service that provides functionality to manage the submission of a question.
 *
 * @see questionnaire -> questionnaire.services.yml
 */
class SubmissionManager {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a SubmissionManager object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger,) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Loads the respective submission of the question by the current user.
   *
   * @param \Drupal\questionnaire\Entity\Question $question
   *   The requested question.
   *
   * @returns \Drupal\questionnaire\Entity\QuestionSubmission|null
   *   The respective submission or NULL if no storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function loadSubmission(Question $question) : ?QuestionSubmission {

    // Get referenced submission.
    $query = $this->entityTypeManager
      ->getStorage('question_submission')
      ->getQuery();
    $submission_id = $query->condition('question', $question->id())
      ->condition('uid', $this->currentUser->id())
      ->execute();

    // Return nothing if there is no submission.
    if (empty($submission_id)) {
      return NULL;
    }

    // Something went wrong here.
    if (count($submission_id) > 1) {
      throw new EntityMalformedException('The submission for the requested question has inconsistent persistent data.');
    }

    // Return loaded submission.
    /** @var \Drupal\questionnaire\Entity\QuestionSubmission $submission */
    $submission = $this->entityTypeManager
      ->getStorage('question_submission')
      ->load(reset($submission_id));
    return $submission;
  }

  /**
   * Gets a submission of a question.
   */
  public function getSubmission(Question $entity): ?QuestionSubmission {

    $submission = NULL;

    try {
      $submission = $this->loadSubmission($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Can not retrieve question_submission entity. %type: @message in %function (line %line of %file).', $variables);
    }
    catch (EntityMalformedException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('The submission of the requested question has inconsistent persistent data. %type: @message in %function (line %line of %file).', $variables);
    }

    return $submission;
  }

}
