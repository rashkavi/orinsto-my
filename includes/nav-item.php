<?php

class nav_item
{
    // true if this item, or any descendant, matches the selected key
    private static function isBranchActive($item, $selectedKey)
    {
        if (($item["key"] ?? null) === $selectedKey) {
            return true;
        }
        foreach ($item["children"] ?? [] as $child) {
            if (self::isBranchActive($child, $selectedKey)) {
                return true;
            }
        }
        return false;
    }

    // renders any depth of sub items
    private static function loadSubItems($children, $selectedKey)
    {
        foreach ($children as $child) {
            $hasChildren = !empty($child["children"]);
            $isBranchActive = self::isBranchActive($child, $selectedKey);

            if ($hasChildren) {
                $show    = $isBranchActive ? "show" : "";
                $rotated = $isBranchActive ? "rotated" : "";
                $active  = $isBranchActive ? "active" : "";
?>
                <div class="sidebar-subgroup <?= $active ?>">
                    <button type="button"
                        class="sidebar-sublink sidebar-subtoggle w-100 border-0 <?= $active ?>"
                        onclick="toggleSidebarSubmenu(this)">
                        <span class="sidebar-sub-branch"></span>
                        <span class="sidebar-sublink-text"><?= $child["title"] ?></span>
                        <iconify-icon icon="mdi:chevron-down" width="16" height="16" class="submenu-arrow sub-arrow <?= $rotated ?>"></iconify-icon>
                    </button>

                    <div class="sidebar-submenu <?= $show ?>">
                        <div class="sidebar-submenu-inner">
                            <?php self::loadSubItems($child["children"], $selectedKey); ?>
                        </div>
                    </div>
                </div>
            <?php
            } else {
                $active = ($child["key"] === $selectedKey) ? "active" : "";
            ?>
                <a href="<?= $child["link"] ?>" class="sidebar-sublink <?= $active ?>">
                    <span class="sidebar-sub-branch"></span>
                    <span class="sidebar-sublink-text"><?= $child["title"] ?></span>
                </a>
            <?php
            }
        }
    }

    public static function loadItem($item, $selectedKey)
    {
        $hasChildren = !empty($item["children"]);
        $isActive = self::isBranchActive($item, $selectedKey);

        $parentActiveClass = $isActive ? "active" : "";
        $submenuClass = $isActive ? "show" : "";
        $toggleClass = $isActive ? "rotated" : "";

        if ($hasChildren) {
        ?>
            <div class="sidebar-group <?= $parentActiveClass ?>">
                <button type="button"
                    class="sidebar-link sidebar-toggle w-100 border-0"
                    onclick="toggleSidebarSubmenu(this)">
                    <div class="sidebar-link-left">
                        <span class="sidebar-icon-wrap">
                            <iconify-icon icon="<?= $item["icon"] ?>" width="20" height="20" class="nav-icon <?= $parentActiveClass ?>"></iconify-icon>
                        </span>
                        <span class="sidebar-link-text"><?= $item["title"] ?></span>
                    </div>

                    <span class="sidebar-link-right">
                        <iconify-icon icon="mdi:chevron-down" width="18" height="18" class="submenu-arrow <?= $toggleClass ?>"></iconify-icon>
                    </span>
                </button>

                <div class="sidebar-submenu <?= $submenuClass ?>">
                    <div class="sidebar-submenu-inner">
                        <?php self::loadSubItems($item["children"], $selectedKey); ?>
                    </div>
                </div>
            </div>
        <?php
        } else {
        ?>
            <a href="<?= $item["link"] ?>" class="sidebar-link single-link <?= $isActive ? 'active' : '' ?>">
                <div class="sidebar-link-left">
                    <span class="sidebar-icon-wrap">
                        <iconify-icon icon="<?= $item["icon"] ?>" width="20" height="20" class="nav-icon"></iconify-icon>
                    </span>
                    <span class="sidebar-link-text"><?= $item["title"] ?></span>
                </div>
            </a>
<?php
        }
    }
}
?>