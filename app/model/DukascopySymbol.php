<?php
namespace rosasurfer\rt\model;

use rosasurfer\console\io\Output;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rt\lib\IHistoryProvider;
use rosasurfer\rt\lib\dukascopy\Dukascopy;

use function rosasurfer\rt\fxDate;
use function rosasurfer\rt\periodDescription;
use function rosasurfer\rt\periodToStr;

use const rosasurfer\rt\PERIOD_TICK;
use const rosasurfer\rt\PERIOD_M1;
use const rosasurfer\rt\PERIOD_H1;
use const rosasurfer\rt\PERIOD_D1;


/**
 * DukascopySymbol
 *
 * Represents a Dukascopy symbol.
 *
 * @method string     getName()       Return the symbol name, i.e. the actual symbol.
 * @method int        getDigits()     Return the number of fractional digits of symbol prices.
 * @method RosaSymbol getRosaSymbol() Return the Rosatrader symbol this Dukascopy symbol is mapped to.
 */
class DukascopySymbol extends RosatraderModel implements IHistoryProvider {


    /** @var string - symbol name */
    protected $name;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var string - start time of the available tick history (FXT) */
    protected $historyStartTick;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyStartM1;

    /** @var string - start time of the available H1 history (FXT) */
    protected $historyStartH1;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyStartD1;

    /** @var RosaSymbol [transient] - the Rosatrader symbol this Dukascopy symbol is mapped to */
    protected $rosaSymbol;


    /**
     * Return the instrument's quote resolution (the value of 1 point).
     *
     * @return double
     */
    public function getPointValue() {
        return 1/pow(10, $this->digits);
    }


    /**
     * Return the start time of the symbol's available tick history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartTick) || $format=='Y-m-d H:i:s')
            return $this->historyStartTick;
        return gmdate($format, strtotime($this->historyStartTick.' GMT'));
    }


    /**
     * Return the start time of the symbol's available M1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartM1) || $format=='Y-m-d H:i:s')
            return $this->historyStartM1;
        return gmdate($format, strtotime($this->historyStartM1.' GMT'));
    }


    /**
     * Return the start time of the symbol's available H1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartH1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartH1) || $format=='Y-m-d H:i:s')
            return $this->historyStartH1;
        return gmdate($format, strtotime($this->historyStartH1.' GMT'));
    }


    /**
     * Return the start time of the symbol's available D1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartD1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartD1) || $format=='Y-m-d H:i:s')
            return $this->historyStartD1;
        return gmdate($format, strtotime($this->historyStartD1.' GMT'));
    }


    /**
     * Show history status information.
     *
     * @param  bool $local [optional] - whether to show local or remotely fetched history status
     *                                  (default: local)
     * @return bool - success status
     */
    public function showHistoryStatus($local = true) {
        if (!is_bool($local)) throw new IllegalTypeException('Illegal type of parameter $local: '.gettype($local));
        /** @var Output $output */
        $output = $this->di(Output::class);

        if ($local) {
            $startTick = $this->getHistoryStartTick('D, d-M-Y H:i:s \F\X\T');
            $startM1   = $this->getHistoryStartM1  ('D, d-M-Y H:i:s \F\X\T');
            $startH1   = $this->getHistoryStartH1  ('D, d-M-Y H:i:s \F\X\T');
            $startD1   = $this->getHistoryStartD1  ('D, d-M-Y H:i:s \F\X\T');

            if (!$startTick && !$startM1 && !$startH1 && !$startD1) {
                $output->out('[Info]    '.$this->name.'  local Dukascopy status not available');
            }
            else {
                $startTick && $output->out('[Info]    '.$this->name.'  Dukascopy TICK history starts '.$startTick);
                $startM1   && $output->out('[Info]    '.$this->name.'  Dukascopy M1   history starts '.$startM1  );
                $startH1   && $output->out('[Info]    '.$this->name.'  Dukascopy H1   history starts '.$startH1  );
                $startD1   && $output->out('[Info]    '.$this->name.'  Dukascopy D1   history starts '.$startD1  );
            }
        }
        else {
            /** @var Dukascopy $dukascopy */
            $dukascopy = $this->di(Dukascopy::class);
            $historyStart = $dukascopy->fetchHistoryStart($this);

            foreach ($historyStart as $timeframe => $time) {
                $period     = periodDescription($timeframe);
                $datetime   = \DateTime::createFromFormat(is_int($time) ? 'U':'U.u', is_int($time) ? (string)$time : number_format($time, 6, '.', ''));
                $formatted  = $datetime->format('D, d-M-Y H:i'.(is_int($time) ? '':':s.u'));
                is_float($time) && $formatted = strLeft($formatted, -3);
                $formatted .= ' FXT';
                $output->out('[Info]    '.$this->name.'  Dukascopy '.str_pad($period, 4).' history starts '.$formatted);
            }
        }
        return true;
    }


    /**
     * Update locally stored history start times with the passed data.
     *
     * @param  array $times - array with start times per timeframe
     *
     * @return bool - whether at least one of the start times have changed
     */
    public function updateHistoryStart(array $times) {
        /** @var Output $output */
        $output = $this->di(Output::class);

        $localTime = $this->historyStartTick;
        $remoteTime = isset($times[PERIOD_TICK]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_TICK], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartTick = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  TICK history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartM1;
        $remoteTime = isset($times[PERIOD_M1]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_M1], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartM1 = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  M1   history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartH1;
        $remoteTime = isset($times[PERIOD_H1]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_H1], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartH1 = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  H1   history start changed: '.($remoteTime ?: 'n/a'));
        }

        $localTime = $this->historyStartD1;
        $remoteTime = isset($times[PERIOD_D1]) ? fxDate('Y-m-d H:i:s', (int)$times[PERIOD_D1], true) : null;
        if ($localTime !== $remoteTime) {
            $this->historyStartD1 = $remoteTime;
            $this->modified();
            $output->out('[Info]    '.$this->getName().'  D1   history start changed: '.($remoteTime ?: 'n/a'));
        }
        return $this->isModified();
    }


    /**
     * {@inheritdoc}
     */
    public function getHistory($period, $time, $optimized = false) {
        if (!is_int($period))     throw new IllegalTypeException('Illegal type of parameter $period: '.gettype($period));
        if ($period != PERIOD_M1) throw new UnimplementedFeatureException(__METHOD__.'('.periodToStr($period).') not implemented');
        if (!is_int($time))       throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        if (!$time) {
            if (!$start = (int) $this->getHistoryStartM1('U')) {
                /** @var Output $output */
                $output = $this->di(Output::class);
                $output->error('[Error]   '.str_pad($this->name, 6).'  history start for M1 not available');
                return [];
            }
            $time = $start;
        }

        /** @var Dukascopy $dukascopy */
        $dukascopy = $this->di(Dukascopy::class);
        return $dukascopy->getHistory($this, $period, $time, $optimized);
    }
}
