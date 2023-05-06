<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\user_types\Utility\Profile;

/**
 * Computes task of project comment owner.
 */
class ProjectCommentTaskFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function computeValue() {

    if (!isset($this->list[0])) {

      /** @var \Drupal\projects\ProjectCommentInterface $comment */
      $comment = $this->getEntity();
      /** @var \Drupal\projects\ProjectInterface $project */
      $project = $comment->getOriginEntity();

      // Resolve task of participant or author.
      if (Profile::isCreative($comment->getOwner())) {
        $participants = $project->getParticipants();
        $participants_filtered = array_filter($participants, fn($p) => $p->id() == $comment->getOwnerId());
        $participant = reset($participants_filtered);
        /** @var string $task */
        $task = $participant->task ?? '';
        $task = strtolower($task);
      }
      elseif ($project->isAuthor($comment->getOwner())) {
        $task = 'organization';
      }
      else {
        $task = '';
      }

      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableStringItem $item */
      $item = $this->createItem(0, $task);
      $item->getValueProperty()->addCacheableDependency($comment->getOwner())
        ->addCacheableDependency($project)
        ->addCacheableDependency($project->getOwner());
      $this->list[0] = $item;
    }
  }

}
