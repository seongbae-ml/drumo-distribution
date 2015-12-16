<?php
namespace Drupal\drumo\Plugin\rest\resource;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get nodes.
 *
 * @RestResource(
 *   id = "drumo_nodes",
 *   label = @Translation("Get nodes by content type"),
 *   uri_paths = {
 *     "canonical" = "/drumo/nodes/{entity}/{offset}/{limit}/{sort}/{direction}"
 *   }
 * )
 */

class NodesResource extends ResourceBase {
  /**
   *  A curent user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  /**
   *  A instance of entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity.manager'),
      $container->get('current_user')
    );
  }

  /**
  * Constructs a Drupal\rest\Plugin\ResourceBase object.
  *
  * @param array $configuration
  *   A configuration array containing information about the plugin instance.
  * @param string $plugin_id
  *   The plugin_id for the plugin instance.
  * @param mixed $plugin_definition
  *   The plugin implementation definition.
  * @param array $serializer_formats
  *   The available serialization formats.
  * @param \Psr\Log\LoggerInterface $logger
  *   A logger instance.
  */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityManagerInterface $entity_manager,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->entityManager = $entity_manager;
    $this->currentUser = $current_user;
  }

  /*
   * Responds to GET requests.
   *
   * Returns a list of nodes for specified entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing a list of bundle names.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($entityType, $offset=0, $limit=10, $sort='nid', $direction='ASC') {
    
    if ($entityType) {
      $permission = 'View published content';
      if(!$this->currentUser->hasPermission($permission)) {
        throw new AccessDeniedHttpException();
      }
      
      $query = \Drupal::entityQuery('node')
          ->condition('type',$entityType)
          ->condition('status',NODE_PUBLISHED)
          ->sort($sort, $direction)
          ->range($offset,$limit);

      $nids = $query->execute();
      $queryNodes = entity_load_multiple('node', $nids);

      \Drupal::logger('drumo')->debug("offset: @offset, limit: @limit",
                          array('@offset' => $offset,'@limit' => $limit));

      //$query->entityCondition('entity_type', 'node')
      //->entityCondition('bundle', $entityType)
      //->propertyCondition('status', NODE_PUBLISHED)
      //->fieldCondition('field_news_types', 'value', 'spotlight', '=')
      //->fieldCondition('field_photo', 'fid', 'NULL', '!=')
      //->fieldCondition('field_faculty_tag', 'tid', $value)
      //->fieldCondition('field_news_publishdate', 'value', $year . '%', 'like')
      //->fieldOrderBy('field_photo', 'fid', 'DESC')
      //->range(0, 10);
      //->addMetaData('account', user_load(1)); // Run the query as user 1.

      //$result = $query->execute();

      $nodes = array();
      
      foreach ($queryNodes as $node) {
        $field_names = array_keys($node->getFieldDefinitions());
        
        $fields = array();
        foreach ($field_names as $field_name) {
            $fields[$field_name] = $node->get($field_name)->value;
        }

        $nodes[$node->id()] = $fields;
      }

      if (!empty($nodes)) {
        return new ResourceResponse($nodes);
      }
      throw new NotFoundHttpException(t('No content found for @entityType were not found', array('@entityType' => $entityType)));
    }

    throw new HttpException(t('Entity Type was not provided'));
  }
}

