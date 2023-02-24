import { override } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import listItems from 'flarum/common/helpers/listItems';
import DiscussionList from 'flarum/forum/components/DiscussionList';
import Search from 'flarum/forum/components/Search';
import app from 'flarum/forum/app';

export default function () {
  override(IndexPage.prototype, 'view', function (original) {
    return (
      <div className="IndexPage">
      {this.hero()}
      <div className="container">
        <div className="sideNavContainer">
          <nav className="IndexPage-nav sideNav">
            <ul>{listItems(this.sidebarItems().toArray())}</ul>
          </nav>
          <div className="IndexPage-results sideNavOffset">
            <div className='IndexPage-searchbar'>
              <Search state={app.search} />
            </div>
            <div className="IndexPage-toolbar">
              <ul className="IndexPage-toolbar-view">{listItems(this.viewItems().toArray())}</ul>
              <ul className="IndexPage-toolbar-action">{listItems(this.actionItems().toArray())}</ul>
            </div>
            <DiscussionList state={app.discussions} />
          </div>
        </div>
      </div>
    </div>
    )
  });
}
