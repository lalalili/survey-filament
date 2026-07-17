<?php

namespace Google;

if (! class_exists(Client::class)) {
    class Client
    {
        /** @var array<int, string> */
        private array $scopes = [];

        /**
         * @param  array<int, string>  $scopes
         */
        public function setScopes(array $scopes): void
        {
            $this->scopes = array_values($scopes);
        }

        /**
         * @return array<int, string>
         */
        public function getScopes(): array
        {
            return $this->scopes;
        }
    }
}
