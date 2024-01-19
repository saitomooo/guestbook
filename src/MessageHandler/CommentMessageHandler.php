<?php
namespace App\MessageHandler;
use App\ImageOptimizer;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Notifier\NotifierInterface;
use App\Notification\CommentReviewNotification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsMessageHandler]
class CommentMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SpamChecker $spamChecker,
        private CommentRepository $commentRepository,
        private MessageBusInterface $bus,
        private WorkflowInterface $commentStateMachine,
        private ImageOptimizer $imageOptimizer,
        private NotifierInterface $notifier,
        #[Autowire('%photo_dir%')] private string $photoDir,
        private ?LoggerInterface $logger = null,
    ){

    }
    public function __invoke(CommentMessage $message)
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            $this->logger?->debug('Comment not found', ['id' => $message->getId()]);
            return;
        }

        if ($this->commentStateMachine->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = match ($score) {
                2 => 'reject_spam',
                1 => 'might_be_spam',
                default => 'accept',
            };
            $this->commentStateMachine->apply($comment, $transition);
            $this->entityManager->flush();
            $this->bus->dispatch($message);
            $this->logger?->debug('Processed comment: accept', ['comment' => $comment->getId(), 'transition' => $transition]);
        } elseif ($this->commentStateMachine->can($comment, 'publish') || $this->commentStateMachine->can($comment, 'publish_ham')) {
            try {
                $this->notifier->send(new CommentReviewNotification($comment), ...$this->notifier->getAdminRecipients());
                $this->logger?->debug('Mail sent for comment', ['comment' => $comment->getId()]);
            } catch (\Throwable $e) {
                $this->logger?->error('Mail sending failed', ['exception' => $e->getMessage()]);
            }
        } elseif ($this->commentStateMachine->can($comment, 'optimize')) {
                        if ($comment->getPhotoFilename()) {
                            $this->imageOptimizer->resize($this->photoDir.'/'.$comment->getPhotoFilename());
                        }
                        $this->commentStateMachine->apply($comment, 'optimize');
                        $this->entityManager->flush();
        } elseif ($this->logger) {
            $this->logger?->debug('Dropping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
        }
    }
}
