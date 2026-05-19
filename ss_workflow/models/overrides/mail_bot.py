from odoo import models, fields, exceptions, api

class MailBot(models.AbstractModel):
    _inherit = 'mail.bot'
    
    
    def _get_answer(self, record, body, values, command=False):
        return False