<?php
namespace Egulias\EmailValidator\Parser;

use Egulias\EmailValidator\EmailLexer;
use Egulias\EmailValidator\Warning\CFWSNearAt;
use Egulias\EmailValidator\Result\InvalidEmail;
use Egulias\EmailValidator\Warning\CFWSWithFWS;
use Egulias\EmailValidator\Result\Reason\CRNoLF;
use Egulias\EmailValidator\Result\Reason\AtextAfterCFWS;
use Egulias\EmailValidator\Result\Reason\CRLFAtTheEnd;
use Egulias\EmailValidator\Result\Reason\CRLFX2;
use Egulias\EmailValidator\Result\Reason\ExpectingCTEXT;
use Egulias\EmailValidator\Result\ValidEmail;

class  FoldingWhiteSpace extends Parser
{
    public function parse($str)
    {
        if (!$this->isFWS()) {
            return new ValidEmail();
        }

        $previous = $this->lexer->getPrevious();

        $this->checkCRLFInFWS();

        if ($this->lexer->token['type'] === EmailLexer::S_CR) {
            return new InvalidEmail(new CRNoLF(), $this->lexer->token['value']);
        }

        if ($this->lexer->isNextToken(EmailLexer::GENERIC) && $previous['type']  !== EmailLexer::S_AT) {
            return new InvalidEmail(new AtextAfterCFWS(), $this->lexer->token['value']);
        }

        if ($this->lexer->token['type'] === EmailLexer::S_LF || $this->lexer->token['type'] === EmailLexer::C_NUL) {
            return new InvalidEmail(new ExpectingCTEXT(), $this->lexer->token['value']);
        }

        if ($this->lexer->isNextToken(EmailLexer::S_AT) || $previous['type']  === EmailLexer::S_AT) {
            $this->warnings[CFWSNearAt::CODE] = new CFWSNearAt();
        } else {
            $this->warnings[CFWSWithFWS::CODE] = new CFWSWithFWS();
        }

        return new ValidEmail();
    }

    protected function checkCRLFInFWS()
    {
        if ($this->lexer->token['type'] !== EmailLexer::CRLF) {
            return new ValidEmail();
        }

        if (!$this->lexer->isNextTokenAny(array(EmailLexer::S_SP, EmailLexer::S_HTAB))) {
            return new InvalidEmail(new CRLFX2(), $this->lexer->token['value']);
        }

        //this has no coverage. Condition is repeated from above one
        if (!$this->lexer->isNextTokenAny(array(EmailLexer::S_SP, EmailLexer::S_HTAB))) {
            return new InvalidEmail(new CRLFAtTheEnd(), $this->lexer->token['value']);
        }
    }
     
    /**
     * @return bool
     */
    protected function isFWS()
    {
        if ($this->escaped()) {
            return false;
        }

        return $this->lexer->token['type'] === EmailLexer::S_SP ||
            $this->lexer->token['type'] === EmailLexer::S_HTAB ||
            $this->lexer->token['type'] === EmailLexer::S_CR ||
            $this->lexer->token['type'] === EmailLexer::S_LF ||
            $this->lexer->token['type'] === EmailLexer::CRLF;
    }

    /**
     * @return bool
     */
    protected function escaped()
    {
        $previous = $this->lexer->getPrevious();

        return $previous && $previous['type'] === EmailLexer::S_BACKSLASH
            &&
            $this->lexer->token['type'] !== EmailLexer::GENERIC;
    }

}