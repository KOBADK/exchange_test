<?php

namespace Itk\KobaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

use PhpEws\EWSType\CalendarViewType;
use PhpEws\EWSType\ConnectingSIDType;
use PhpEws\EWSType\DefaultShapeNamesType;
use PhpEws\EWSType\DeleteItemType;
use PhpEws\EWSType\DistinguishedFolderIdNameType;
use PhpEws\EWSType\DistinguishedFolderIdType;
use PhpEws\EWSType\ExchangeImpersonationType;
use PhpEws\EWSType\FindItemType;
use PhpEws\EWSType\ItemQueryTraversalType;
use PhpEws\EWSType\ItemResponseShapeType;
use PhpEws\EWSType\NonEmptyArrayOfBaseFolderIdsType;
use PhpEws\EWSType\CalendarItemCreateOrDeleteOperationType;
use PhpEws\EWSType\ItemIdType;
use PhpEws\EWSType\NonEmptyArrayOfBaseItemIdsType;
use PhpEws\EWSType\DisposalType;
use PhpEws\ExchangeWebServices;

/**
 * @Route("/ews")
 */
class DefaultController extends Controller {
  private $ews;

  public function __construct() {
    $this->ews = new ExchangeWebServices('exch.dhost.dk', 'dhost\tj_UM', 'something', ExchangeWebServices::VERSION_2010);
  }

  /**
   * @Route("/list")
   */
  public function listAction() {
    // Configure impersonation
    $ei = new ExchangeImpersonationType();
    $sid = new ConnectingSIDType();
    $sid->PrimarySmtpAddress = 'test@unitmakers.dk';
    $ei->ConnectingSID = $sid;
    $this->ews->setImpersonation($ei);

    $request = new FindItemType();
    $request->Traversal = ItemQueryTraversalType::SHALLOW;

    $request->ItemShape = new ItemResponseShapeType();
    $request->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;

    $request->CalendarView = new CalendarViewType();
    $request->CalendarView->StartDate = strtotime('12/01/2014');
    $request->CalendarView->EndDate = strtotime('12/31/2015');

    $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
    $request->ParentFolderIds->DistinguishedFolderId = new DistinguishedFolderIdType();
    $request->ParentFolderIds->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::CALENDAR;

    $response = $this->ews->FindItem($request);

    // Verify the response.
    if ($response->ResponseMessages->FindItemResponseMessage->ResponseCode == "NoError") {
      // Verify items.
      if ($response->ResponseMessages->FindItemResponseMessage->RootFolder->TotalItemsInView > 0) {
        return $this->render('ItkKobaBundle:Default:list.html.twig', array('items' => $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->CalendarItem));
      }
    }

    return $this->render('ItkKobaBundle:Default:index.html.twig');
  }

  /**
   * @Route("/create")
   */
  public function createAction() {
    // What resource?
    $to = 'test@unitmakers.dk';

    // From who?
    $from = 'tj@unitmakers.dk';
    $organizer = 'Thomas Johansen';
    $organizer_email = 'tj@unitmakers.dk';

    // Location.
    $location = "Stardestroyer-013";

    // Date
    $date = '20141231';
    $startTime = '0800';
    $endTime = '0900';

    // Subject
    $subject = 'Millennium Falcon';

    // Description
    $desc = 'The purpose of the meeting is to discuss the capture of Millennium Falcon and its crew.';

    // Setup headers.
    $headers = 'Content-Type:text/calendar; charset=utf-8; method=REQUEST\r\n';
    $headers .= "Content-Type: text/plain;charset=\"utf-8\" \r\n";

    // vCard.
    $message = "BEGIN:VCALENDAR\r\n
    VERSION:2.0\r\n
    PRODID:-//Deathstar-mailer//theforce/NONSGML v1.0//EN\r\n
    METHOD:REQUEST\r\n
    BEGIN:VEVENT\r\n
    UID:" . md5(uniqid(mt_rand(), true)) . "example.com\r\n
    DTSTAMP:" . gmdate('Ymd').'T'. gmdate('His') . "Z\r\n
    DTSTART:".$date."T".$startTime."00Z\r\n
    DTEND:".$date."T".$endTime."00Z\r\n
    SUMMARY:".$subject."\r\n
    ORGANIZER;CN=".$organizer.":mailto:".$organizer_email."\r\n
    LOCATION:".$location."\r\n
    DESCRIPTION:".$desc."\r\n
    END:VEVENT\r\n
    END:VCALENDAR\r\n";

    // Send the e-mail.
    mail($to, $subject, $message, $headers, "-f $from");

    return $this->render('ItkKobaBundle:Default:index.html.twig');
  }

  /**
   * @param Request $req
   *   The request object.
   *
   * @return array

   * @Route("/delete")
   * @Method("POST")
   */
  public function deleteAction(Request $req) {
    // Id and ChangeKey arguments.
    $event_id = $req->request->get('event_id');
    $event_change_key = $req->request->get('event_change_key');
    if (!$event_id || !$event_change_key) {
      return $this->render('ItkKobaBundle:Default:index.html.twig');
    }

    // Define the delete item class.
    $request = new DeleteItemType();

    // Send to trash can, or use DisposalType::HARD_DELETE instead to
    // bypass the bin directly.
    $request->DeleteType = DisposalType::MOVE_TO_DELETED_ITEMS;

    // Inform no one who shares the item that it has been deleted.
    $request->SendMeetingCancellations = CalendarItemCreateOrDeleteOperationType::SEND_TO_NONE;

    // Set the item to be deleted.
    $item = new ItemIdType();
    $item->Id = $event_id;
    $item->ChangeKey = $event_change_key;

    // We can use this to mass delete but in this case it's just one item.
    $items = new NonEmptyArrayOfBaseItemIdsType();
    $items->ItemId = $item;
    $request->ItemIds = $items;

    // Send the request.
    $response = $this->ews->DeleteItem($request);

    // Verify the response.
    if ($response->ResponseMessages->FindItemResponseMessage->ResponseCode == "NoError") {

    }

    return $this->render('ItkKobaBundle:Default:index.html.twig');
  }
}
