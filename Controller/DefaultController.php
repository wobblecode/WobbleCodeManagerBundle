<?php

namespace WobbleCode\ManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use JMS\SecurityExtraBundle\Annotation\PreAuthorize;
use JMS\SecurityExtraBundle\Annotation\Secure;
use WobbleCode\RestBundle\Configuration\Rest;

class DefaultController extends Controller
{
    /**
     * Get the generic manager for this Controller
     *
     * @return DocumentManager
     */
    protected function getManager()
    {
        $gdm = $this->get('wobblecode_manager.document_manager')
                    ->setDocument('WobbleCodeUserBundle:Organization')
                    ->setKey('organization')
                    ->setAcceptFromRequest(['page', 'query', 'itemsPerPage'])
                    ->setItemsPerPage(2)
                    ->setQueryFields(['name', 'type']);

        return $gdm;
    }

    /**
     * @Route("/test/manager", name="test_manager")
     * @Template()
     * @Rest(output={"entities", "groups", "metadata"})
     */
    public function indexAction()
    {
        $gdm = $this->getManager();

        return [
            'entities' => $gdm->getDocuments(),
            'groups'   => $gdm->countByGroup('type')
        ];
    }
}
