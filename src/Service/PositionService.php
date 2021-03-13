<?php


namespace App\Service;


use App\Entity\Portfolio;
use App\Entity\Position;
use Doctrine\ORM\EntityManagerInterface;

class PositionService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function openPosition(Portfolio $portfolio, string $ticker, int $amount, int $openingPrice): Position
    {
        /** @var Position $position */
        $position = $this->em->getRepository(Position::class)->findOneBy([
           'portfolio' => $portfolio,
           'ticker' => $ticker
        ]);

        if(!$position)
        {
            return $this->createPosition($portfolio, $ticker, $amount, $openingPrice);
        }
        else
        {
            return $this->addPosition($position, $amount, $openingPrice);
        }
    }

    public function createPosition(Portfolio $portfolio, string $ticker, int $amount, int $openingPrice): Position
    {
        $position = new Position($ticker, $amount, $openingPrice, $portfolio);

        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    public function addPosition(Position $position, int $amount, int $openingPrice): Position
    {
        $position->setAveragePrice($this->calculateNewAveragePrice($position, $amount, $openingPrice));

        $position->setAmount($position->getAmount() + $amount);

        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    private function calculateNewAveragePrice(Position $position, int $amount, int $openingPrice): int
    {
        return $newAveragePrice = ( $position->getAmount() * $position->getAveragePrice() + $amount * $openingPrice ) / ( $position->getAmount() + $amount );
    }

    public function closePosition(Portfolio $portfolio, string $ticker, int $amount, int $closingPrice): ?Position
    {
        /** @var Position $position */
        $position = $this->em->getRepository(Position::class)->findOneBy([
            'portfolio' => $portfolio,
            'ticker' => $ticker
        ]);

        if($position === null)
        {
            throw new \Exception("There exists no position for Ticker: " . $ticker . " in Portfolio ID: " . $portfolio->getId());
        }

        if($position->getAmount() < $amount)
        {
            throw new \Exception("Not enough positions to close. Amount: " . $position->getAmount());
        }

        $position->setAmount($position->getAmount() - $amount);

        if($position->getAmount() == 0)
        {
            $this->em->remove($position);
        }
        else
        {
            $this->em->persist($position);
        }

        $this->em->flush();

        return $position;
    }

    public function displayAveragePrice(Position $position): float
    {
        return $position->getAveragePrice() / $position::CONVERSION_FACTOR;
    }

}