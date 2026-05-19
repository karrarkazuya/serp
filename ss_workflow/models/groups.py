# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions


class ssw_groups(models.Model):
    _name = 'ssw.groups'
    _description = 'Group'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title', required=True, tracking=True)
    deleted    = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
    
