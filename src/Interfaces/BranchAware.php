<?php

namespace Sosupp\SlimerDesktop\Interfaces;

interface BranchAware
{
    public function getBranchUid(): ?string;
}