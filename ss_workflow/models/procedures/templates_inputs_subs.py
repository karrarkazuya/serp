# -*- coding: utf-8 -*-

from odoo import models, fields, api


class ssw_templates_inputs_subs(models.Model):
    _name = 'ssw.proc.templates.inputs.subs'
    _description = 'task Input Subs'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name       = fields.Char(string='Title')
    input_id   = fields.Many2one('ssw.proc.templates.inputs', string='Template Input', required=True, tracking=True)
    deleted    = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True

