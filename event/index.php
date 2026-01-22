<?php
    if(isset($_GET['event_code'])) {
        header("Location: /event/".$_GET['event_code'] . "/hello");
    } else {
        header("Location: admin");
    }
?>