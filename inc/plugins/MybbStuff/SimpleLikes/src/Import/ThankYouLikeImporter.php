<?php
declare(strict_types=1);

namespace MybbStuff\SimpleLikes\Import;

final class ThankYouLikeImporter extends AbstractImporter
{
    /**
     * Get the title of the importer.
     *
     * @return string The title of the importer.
     */
    public function getTitle(): string
    {
        return 'ThankYou Like Importer';
    }

    /**
     * Get the description for the importer.
     *
     * @return string A short description of the importer.
     */
    public function getDescription(): string
    {
        return "Imports likes from G33K's ThankYouLike plugin into SimpleLikes.";
    }

    /**
     * Perform the conversion of the likes from the 3rd party system.
     *
     * @return int The number of converted likes.
     */
    public function importLikes(): int
    {
        $newLikes = [];
        $query = $this->db->simple_select('g33k_thankyoulike_thankyoulike', 'pid, uid');
        $numConverted = 0;
        while ($like = $this->db->fetch_array($query)) {
            $newLikes[] = [
                'post_id' => (int)$like['pid'],
                'user_id' => (int)$like['uid'],
            ];
            ++$numConverted;
        }

        $this->db->insert_query_multiple('post_likes', $newLikes);

        return $numConverted;
    }
}
