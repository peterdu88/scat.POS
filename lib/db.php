<?
class ScatDB extends mysqli {
  public function escape($text) {
    return parent::real_escape_string($text);
  }

  public function get_one($q) {
    $r= $this->query($q);
    if (!$r || !$r->num_rows) return false;
    $row= $r->fetch_row();
    return $row[0];
  }

  public function get_one_assoc($q) {
    $r= $this->query($q);
    if (!$r || !$r->num_rows) return false;
    return $r->fetch_assoc();
  }

  public function start_transaction() {
    return $this->query("START TRANSACTION");
  }
}
